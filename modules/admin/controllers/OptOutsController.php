<?php

namespace app\modules\admin\controllers;

use app\models\Entity;
use app\models\OptOutRequest;
use app\services\Mail;
use app\services\OptOutMatcher;
use Yii;
use yii\db\Query;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

class OptOutsController extends AdminController
{
    private const PAGE_SIZE = 50;
    public const STATUSES = ['pending' => 'Pending', 'done' => 'Done', 'rejected' => 'Rejected'];

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => ['resolve' => ['post'], 'reject' => ['post'], 'reopen' => ['post']]];
        return $behaviors;
    }

    public function actionIndex(string $status = 'pending', int $page = 1)
    {
        $status = isset(self::STATUSES[$status]) || $status === 'all' ? $status : 'pending';
        $counts = array_column((new Query())->select(['status', 'n' => 'COUNT(*)'])->from('opt_out_requests')->groupBy('status')->all(), 'n', 'status');
        $query = (new Query())->from('opt_out_requests');
        if ($status !== 'all') {
            $query->where(['status' => $status]);
        }
        $total = (int) (clone $query)->count();
        $page = min(max(1, $page), max(1, (int) ceil($total / self::PAGE_SIZE)));
        $items = $query->orderBy($status === 'pending' ? ['id' => SORT_ASC] : ['id' => SORT_DESC])->limit(self::PAGE_SIZE)->offset(($page - 1) * self::PAGE_SIZE)->all();

        return $this->render('index', [
            'items' => $items,
            'status' => $status,
            'counts' => $counts,
            'total' => $total,
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
        ]);
    }

    public function actionView(int $id, string $ref = '')
    {
        $request = $this->find($id);
        $linked = $request->entity_id ? Entity::find()->select(['id', 'state', 'state_filing_number', 'name', 'people_hidden'])->where(['id' => $request->entity_id])->asArray()->one() : null;
        $lookup = trim($ref) !== '' ? trim($ref) : $request->entity_ref;
        return $this->render('view', [
            'request' => $request,
            'linked' => $linked ?: null,
            'candidates' => $request->status === 'pending' ? OptOutMatcher::candidates($lookup) : [],
            'lookup' => $lookup,
        ]);
    }

    public function actionResolve(int $id)
    {
        $request = $this->find($id);
        $entity = Entity::findOne((int) Yii::$app->request->post('entity_id'));
        if ($request->status !== 'pending') {
            Yii::$app->session->setFlash('error', 'This request is already closed.');
        } elseif ($entity === null) {
            Yii::$app->session->setFlash('error', 'Choose the company to remove people from.');
        } else {
            $tx = Yii::$app->db->beginTransaction();
            Yii::$app->db->createCommand()->update('entities', ['people_hidden' => 1], ['id' => $entity->id])->execute();
            $request->status = 'done';
            $request->entity_id = $entity->id;
            $request->entity_state = $entity->state;
            $request->entity_url_key = $entity->url_key;
            $this->close($request);
            $tx->commit();
            $sent = Mail::optOutDone($request, $entity);
            Yii::$app->session->setFlash($sent ? 'success' : 'warning', 'Personal details are now hidden on ' . $entity->name . '. ' . $this->mailNote($sent, $request->email));
        }
        return $this->redirect(['view', 'id' => $request->id]);
    }

    public function actionReject(int $id)
    {
        $request = $this->find($id);
        if ($request->status !== 'pending') {
            Yii::$app->session->setFlash('error', 'This request is already closed.');
        } else {
            $request->status = 'rejected';
            $this->close($request);
            $sent = Mail::optOutRejected($request);
            Yii::$app->session->setFlash($sent ? 'success' : 'warning', 'Request rejected. ' . $this->mailNote($sent, $request->email));
        }
        return $this->redirect(['view', 'id' => $request->id]);
    }

    public function actionReopen(int $id)
    {
        $request = $this->find($id);
        if ($request->status === 'pending') {
            return $this->redirect(['view', 'id' => $request->id]);
        }
        $tx = Yii::$app->db->beginTransaction();
        if ($request->status === 'done' && $request->entity_state !== null && $request->entity_url_key !== null) {
            $otherDone = (new Query())->from('opt_out_requests')
                ->where(['status' => 'done', 'entity_state' => $request->entity_state, 'entity_url_key' => $request->entity_url_key])
                ->andWhere(['<>', 'id', $request->id])->exists();
            if (!$otherDone) {
                Yii::$app->db->createCommand()->update('entities', ['people_hidden' => 0], ['state' => $request->entity_state, 'url_key' => $request->entity_url_key])->execute();
            }
        }
        $request->status = 'pending';
        $request->entity_id = null;
        $request->entity_state = null;
        $request->entity_url_key = null;
        $request->resolved_at = null;
        $request->resolved_by = null;
        $request->save(false);
        $tx->commit();
        Yii::$app->session->setFlash('success', 'Request reopened. Anything it hid is visible again.');
        return $this->redirect(['view', 'id' => $request->id]);
    }

    private function close(OptOutRequest $request): void
    {
        $note = trim((string) Yii::$app->request->post('note', ''));
        $request->note = $note === '' ? null : mb_substr($note, 0, 500);
        $request->resolved_at = gmdate('Y-m-d H:i:s');
        $request->resolved_by = (int) Yii::$app->user->id;
        $request->save(false);
    }

    private function mailNote(bool $sent, string $email): string
    {
        return $sent ? "We emailed $email to let them know." : "The email to $email could not be sent; reply to them yourself.";
    }

        private function find(int $id): OptOutRequest
    {
        return OptOutRequest::findOne($id) ?? throw new NotFoundHttpException('Request not found.');
    }
}
