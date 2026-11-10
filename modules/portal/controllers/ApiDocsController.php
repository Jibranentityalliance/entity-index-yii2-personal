<?php

namespace app\modules\portal\controllers;

use Yii;

class ApiDocsController extends PortalController
{
    public function actionIndex()
    {
        return $this->render('index', [
            'user' => Yii::$app->user->identity,
            'baseUrl' => rtrim(Yii::$app->params['siteUrl'] ?: Yii::$app->request->hostInfo, '/'),
            'limits' => Yii::$app->params['apiLimits'],
        ]);
    }
}
