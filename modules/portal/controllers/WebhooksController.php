<?php

namespace app\modules\portal\controllers;

use app\models\WebhookDelivery;
use app\models\WebhookEndpoint;
use app\services\Webhooks;
use Yii;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class WebhooksController extends PortalController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => [
            'create' => ['post'], 'update' => ['post'], 'pause' => ['post'], 'resume' => ['post'],
            'rotate' => ['post'], 'delete' => ['post'], 'test' => ['post'], 'redeliver' => ['post'],
        ]];
        return $behaviors;
    }

    public function actionIndex()
    {
        $user = Yii::$app->user->identity;
        $endpoints = $user->hasApiAccess() ? WebhookEndpoint::forUser($user->id)->all() : [];
        return $this->render('index', [
            'user' => $user,
            'endpoints' => $endpoints,
            'model' => new WebhookEndpoint(),
            'max' => (int) Yii::$app->params['webhooks']['maxEndpoints'],
        ]);
    }

    public function actionCreate()
    {
        $user = $this->requireAccess();
        $max = (int) Yii::$app->params['webhooks']['maxEndpoints'];
        if ((int) WebhookEndpoint::forUser($user->id)->count() >= $max) {
            Yii::$app->session->setFlash('error', "You can have at most $max endpoints. Delete one first.");
            return $this->redirect(['index']);
        }
        $model = new WebhookEndpoint(['user_id' => $user->id]);
        $model->load(Yii::$app->request->post());
        if (!$model->save()) {
            return $this->render('index', [
                'user' => $user,
                'endpoints' => WebhookEndpoint::forUser($user->id)->all(),
                'model' => $model,
                'max' => $max,
            ]);
        }
        Yii::$app->session->setFlash('success', 'Endpoint added. New filings that match it will be sent from now on. Send a test event to check your receiver.');
        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionView(int $id)
    {
        $endpoint = $this->find($id);
        return $this->render('view', [
            'endpoint' => $endpoint,
            'deliveries' => WebhookDelivery::find()->where(['endpoint_id' => $endpoint->id])->orderBy(['id' => SORT_DESC])->limit(50)->all(),
            'pending' => (int) WebhookDelivery::find()->where(['endpoint_id' => $endpoint->id, 'status' => 'pending'])->count(),
        ]);
    }

    public function actionUpdate(int $id)
    {
        $endpoint = $this->find($id);
        $endpoint->stateList = $endpoint->typeList = [];
        $endpoint->load(Yii::$app->request->post());
        if ($endpoint->save()) {
            Yii::$app->session->setFlash('success', 'Endpoint saved.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $endpoint->getFirstErrors()));
        }
        return $this->redirect(['view', 'id' => $endpoint->id]);
    }

    public function actionPause(int $id)
    {
        $endpoint = $this->find($id);
        $endpoint->status = WebhookEndpoint::STATUS_PAUSED;
        $endpoint->save(false);
        Yii::$app->session->setFlash('success', 'Endpoint paused. New filings are kept and sent when you resume.');
        return $this->redirect(['view', 'id' => $endpoint->id]);
    }

    public function actionResume(int $id)
    {
        $endpoint = $this->find($id);
        $endpoint->status = WebhookEndpoint::STATUS_ACTIVE;
        $endpoint->disabled_reason = null;
        $endpoint->failure_count = 0;
        $endpoint->save(false);
        Yii::$app->session->setFlash('success', 'Endpoint resumed. Waiting deliveries go out within a minute.');
        return $this->redirect(['view', 'id' => $endpoint->id]);
    }

    public function actionRotate(int $id)
    {
        $endpoint = $this->find($id);
        $endpoint->secret = WebhookEndpoint::newSecret();
        $endpoint->save(false);
        Yii::$app->session->setFlash('success', 'Signing secret replaced. Update your receiver; the old secret no longer matches.');
        return $this->redirect(['view', 'id' => $endpoint->id]);
    }

    public function actionDelete(int $id)
    {
        $endpoint = $this->find($id);
        $endpoint->delete();
        Yii::$app->session->setFlash('success', 'Endpoint deleted.');
        return $this->redirect(['index']);
    }

    public function actionTest(int $id)
    {
        $endpoint = $this->find($id);
        $d = Webhooks::sendTest($endpoint);
        if ($d->status === 'success') {
            Yii::$app->session->setFlash('success', 'Test event delivered: your endpoint answered HTTP ' . $d->response_code . '.');
        } else {
            Yii::$app->session->setFlash('error', 'Test event failed: ' . ($d->error ?? 'no answer') . ' Failed tests are not retried.');
        }
        return $this->redirect(['view', 'id' => $endpoint->id]);
    }

    public function actionRedeliver(int $id, int $delivery)
    {
        $endpoint = $this->find($id);
        $d = WebhookDelivery::findOne(['id' => $delivery, 'endpoint_id' => $endpoint->id]);
        if ($d === null || $d->event === WebhookDelivery::EVENT_TEST) {
            throw new NotFoundHttpException('Delivery not found.');
        }
        Webhooks::redeliver($d);
        Yii::$app->session->setFlash('success', 'Delivery dlv_' . $d->id . ' queued again' . ($endpoint->isActive() ? '; it goes out within a minute.' : '; it goes out when the endpoint is resumed.'));
        return $this->redirect(['view', 'id' => $endpoint->id]);
    }

    private function requireAccess()
    {
        $user = Yii::$app->user->identity;
        if (!$user->hasApiAccess()) {
            throw new ForbiddenHttpException('Your plan does not include webhooks.');
        }
        return $user;
    }

    private function find(int $id): WebhookEndpoint
    {
        $user = $this->requireAccess();
        return WebhookEndpoint::findOne(['id' => $id, 'user_id' => $user->id]) ?? throw new NotFoundHttpException('Endpoint not found.');
    }
}
