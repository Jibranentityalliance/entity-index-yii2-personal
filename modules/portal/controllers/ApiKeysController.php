<?php

namespace app\modules\portal\controllers;

use app\models\ApiKey;
use app\services\ApiUsage;
use Yii;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class ApiKeysController extends PortalController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => ['create' => ['post'], 'revoke' => ['post']]];
        return $behaviors;
    }

    public function actionIndex()
    {
        $user = Yii::$app->user->identity;
        return $this->render('index', [
            'user' => $user,
            'keys' => $user->hasApiAccess() ? ApiKey::activeForUser($user->id)->all() : [],
            'usage' => $user->hasApiAccess() ? ApiUsage::monthSummary($user) : null,
            'max' => ApiKey::MAX_ACTIVE_PER_USER,
            'baseUrl' => rtrim(Yii::$app->params['siteUrl'] ?? Yii::$app->request->hostInfo, '/'),
        ]);
    }

    public function actionCreate()
    {
        $user = Yii::$app->user->identity;
        if (!$user->hasApiAccess()) {
            throw new ForbiddenHttpException('Your plan does not include API access.');
        }
        if (ApiKey::activeCount($user->id) >= ApiKey::MAX_ACTIVE_PER_USER) {
            Yii::$app->session->setFlash('error', 'You can have at most ' . ApiKey::MAX_ACTIVE_PER_USER . ' active keys. Revoke one first.');
            return $this->redirect(['index']);
        }
        $name = trim((string) Yii::$app->request->post('name', ''));
        [, $token] = ApiKey::issue($user, $name !== '' ? $name : 'Untitled key');
        Yii::$app->session->set('newApiKey', $token);
        return $this->redirect(['index']);
    }

    public function actionRevoke()
    {
        $user = Yii::$app->user->identity;
        $key = ApiKey::findOne(['id' => (int) Yii::$app->request->post('id'), 'user_id' => $user->id]);
        if ($key === null) {
            throw new NotFoundHttpException('Key not found.');
        }
        $key->revoke();
        Yii::$app->session->setFlash('success', 'Key ' . $key->key_prefix . '... revoked.');
        return $this->redirect(['index']);
    }
}
