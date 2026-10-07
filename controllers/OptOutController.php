<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

class OptOutController extends Controller
{
    public function actionIndex()
    {
        $ref = Yii::$app->request->get('ref');
        $default = is_string($ref) && str_starts_with($ref, '/') ? Yii::$app->params['siteUrl'] . $ref : '';
        return $this->render('index', ['defaultRef' => $default]);
    }
}
