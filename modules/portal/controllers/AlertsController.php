<?php

namespace app\modules\portal\controllers;

use app\models\SavedSearch;
use app\services\Alerts;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class AlertsController extends PortalController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = [
            'class' => AccessControl::class,
            'rules' => [
                ['allow' => true, 'actions' => ['unsubscribe']],
                ['allow' => true, 'roles' => ['@']],
            ],
        ];
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => [
            'create' => ['post'], 'update' => ['post'], 'toggle' => ['post'], 'delete' => ['post'], 'unsubscribe' => ['get', 'post'],
        ]];
        return $behaviors;
    }

    public function actionIndex()
    {
        $user = Yii::$app->user->identity;
        return $this->render('index', [
            'user' => $user,
            'searches' => $user->hasFullAccess() ? SavedSearch::forUser($user->id)->all() : [],
            'max' => (int) Alerts::cfg('maxSearches'),
        ]);
    }

    public function actionCreate()
    {
        $user = $this->requireAccess();
        $req = Yii::$app->request;
        $filters = SavedSearch::cleanFilters((array) $req->post('filters', []));
        $back = ['/portal/companies/index'] + $filters;
        $max = (int) Alerts::cfg('maxSearches');
        $existing = SavedSearch::findOne(['user_id' => $user->id, 'params_hash' => SavedSearch::hashFilters($filters)]);
        if ($existing !== null) {
            Yii::$app->session->setFlash('info', 'This search is already saved as “' . $existing->name . '”.');
            return $this->redirect($back);
        }
        if ((int) SavedSearch::forUser($user->id)->count() >= $max) {
            Yii::$app->session->setFlash('error', "You can save up to $max searches. Delete one on the Alerts page first.");
            return $this->redirect($back);
        }
        $search = new SavedSearch(['user_id' => $user->id, 'email_enabled' => (int) (bool) $req->post('email', 1)]);
        $search->setFilters($filters);
        $search->name = (string) $req->post('name', '') ?: SavedSearch::describe($filters);
        if (!$search->save()) {
            Yii::$app->session->setFlash('error', implode(' ', $search->getFirstErrors()));
            return $this->redirect($back);
        }
        Yii::$app->session->setFlash('success', $search->email_enabled
            ? 'Search saved. You will get an email each morning when new companies match it.'
            : 'Search saved. Turn on emails on the Alerts page whenever you like.');
        return $this->redirect($back);
    }

    public function actionUpdate(int $id)
    {
        $search = $this->find($id);
        $search->name = (string) Yii::$app->request->post('name', '');
        if ($search->save(true, ['name', 'updated_at'])) {
            Yii::$app->session->setFlash('success', 'Search renamed.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $search->getFirstErrors()));
        }
        return $this->redirect(['index']);
    }

    public function actionToggle(int $id)
    {
        $search = $this->find($id);
        $on = !$search->email_enabled;
        $search->email_enabled = (int) $on;
        if ($on) {
            $search->cursor_at = gmdate('Y-m-d H:i:s');
        }
        $search->save(false, ['email_enabled', 'cursor_at', 'updated_at']);
        Yii::$app->session->setFlash('success', $on ? 'Emails turned on for “' . $search->name . '”. The first one covers companies that go public from now.' : 'Emails turned off for “' . $search->name . '”.');
        return $this->redirect(['index']);
    }

    public function actionDelete(int $id)
    {
        $search = $this->find($id);
        $search->delete();
        Yii::$app->session->setFlash('success', 'Saved search deleted.');
        return $this->redirect(['index']);
    }

    public function actionUnsubscribe(string $token = '')
    {
        $this->layout = 'auth';
        $search = preg_match('/^[a-f0-9]{32}$/', $token) ? SavedSearch::findOne(['unsubscribe_token' => $token]) : null;
        $this->view->registerMetaTag(['name' => 'referrer', 'content' => 'no-referrer']);
        if ($search !== null && Yii::$app->request->isPost && $search->email_enabled) {
            $search->email_enabled = 0;
            $search->save(false, ['email_enabled', 'updated_at']);
            return $this->render('unsubscribe', ['search' => $search, 'done' => true]);
        }
        return $this->render('unsubscribe', ['search' => $search, 'done' => $search !== null && !$search->email_enabled]);
    }

    private function requireAccess()
    {
        $user = Yii::$app->user->identity;
        if (!$user->hasFullAccess()) {
            throw new ForbiddenHttpException('Saved-search alerts are part of the Pro plan and above.');
        }
        return $user;
    }

    private function find(int $id): SavedSearch
    {
        $user = $this->requireAccess();
        return SavedSearch::findOne(['id' => $id, 'user_id' => $user->id]) ?? throw new NotFoundHttpException('Saved search not found.');
    }
}
