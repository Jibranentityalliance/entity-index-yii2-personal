<?php

namespace app\modules\admin\controllers;

use app\models\User;
use app\modules\admin\models\UserForm;
use app\services\ApiUsage;
use app\services\bulk\Bulk;
use app\services\bulk\BulkCatalog;
use app\services\ExportUsage;
use app\services\FreePlan;
use Yii;
use yii\db\Query;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

class UsersController extends AdminController
{
    private const PAGE_SIZE = 50;

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => ['reset-usage' => ['post']]];
        return $behaviors;
    }

    public function actionIndex(string $q = '', string $plan = '', string $role = '', string $status = '', int $page = 1)
    {
        $query = (new Query())->from('user');
        $q = trim($q);
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->andWhere(['or', ['like', 'email', $like, false], ['like', 'name', $like, false]]);
        }
        if (isset(UserForm::PLANS[$plan])) {
            $query->andWhere(['plan' => $plan]);
        }
        if (isset(UserForm::ROLES[$role])) {
            $query->andWhere(['role' => $role]);
        }
        if ($status !== '' && isset(UserForm::STATUSES[(int) $status])) {
            $query->andWhere(['status' => (int) $status]);
        }
        $total = (int) (clone $query)->count();
        $page = min(max(1, $page), max(1, (int) ceil($total / self::PAGE_SIZE)));
        $items = $query->select(['id', 'email', 'name', 'plan', 'role', 'status', 'limit_overrides', 'created_at', 'last_login_at'])
            ->orderBy(['id' => SORT_DESC])->limit(self::PAGE_SIZE)->offset(($page - 1) * self::PAGE_SIZE)->all();

        return $this->render('index', [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
            'filters' => ['q' => $q, 'plan' => $plan, 'role' => $role, 'status' => $status],
        ]);
    }

    public function actionUpdate(int $id)
    {
        $user = $this->find($id);
        $model = UserForm::fromUser($user);
        $self = (int) $user->id === (int) Yii::$app->user->id;
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($self && ($model->role !== $user->role || (int) $model->status !== (int) $user->status)) {
                $model->addError('role', 'You cannot change your own role or disable your own account.');
            } else {
                $model->apply($user);
                $user->save(false);
                Yii::$app->session->setFlash('success', 'Saved ' . $user->email . '.');
                return $this->redirect(['update', 'id' => $user->id]);
            }
        }
        return $this->render('update', [
            'user' => $user,
            'model' => $model,
            'self' => $self,
            'defaults' => $this->defaults($user),
            'usage' => $this->usage($user),
        ]);
    }

    public function actionResetUsage(int $id)
    {
        $user = $this->find($id);
        $month = gmdate('Y-m');
        Yii::$app->db->createCommand()->delete('api_usage', ['and', ['user_id' => $user->id], ['bucket' => ['m:' . $month, 'r:' . $month, 'x:' . $month]]])->execute();
        Yii::$app->db->createCommand()->delete('company_lookup', ['user_id' => $user->id, 'day' => FreePlan::today()])->execute();
        Yii::$app->session->setFlash('success', 'Reset this month\'s usage and today\'s lookups for ' . $user->email . '.');
        return $this->redirect(['update', 'id' => $user->id]);
    }

    private function find(int $id): User
    {
        return User::findOne($id) ?? throw new NotFoundHttpException('User not found.');
    }

    private function defaults(User $user): array
    {
        $p = Yii::$app->params;
        return [
            'api' => $p['apiLimits'][$user->plan] ?? [],
            'export' => $p['exportLimits'][$user->isAdmin() ? 'admin' : $user->plan] ?? [],
            'free' => $p['freeLimits'] ?? [],
            'bulk' => Bulk::config(),
        ];
    }

    private function usage(User $user): array
    {
        $rows = [];
        $fmt = fn(?int $limit) => $limit === null ? 'Unlimited' : number_format($limit);
        if ($user->hasApiAccess()) {
            $a = ApiUsage::monthSummary($user);
            $rows[] = ['API calls this month', number_format($a['used']), $fmt($a['limit'])];
            $rows[] = ['API companies returned this month', number_format($a['rowsUsed']), $fmt($a['rowLimit'])];
        }
        if ($user->hasExportAccess()) {
            $x = ExportUsage::summary($user);
            $rows[] = ['CSV export rows this month', number_format($x['used']), $fmt($x['limit'])];
        }
        if ($user->isFree()) {
            $f = FreePlan::summary($user);
            $rows[] = ['Company lookups today', number_format($f['used']), $fmt($f['lookupsPerDay'])];
        }
        if ($user->hasBulkAccess()) {
            $cap = $user->isAdmin() ? null : (int) $user->withOverrides('bulk', Bulk::config())['downloadsPerDay'];
            $rows[] = ['Bulk downloads today', number_format((new BulkCatalog(Yii::$app->db))->downloadsToday($user->id)), $fmt($cap)];
        }
        return $rows;
    }
}
