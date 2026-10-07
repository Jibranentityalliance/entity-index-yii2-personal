<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

class PricingController extends Controller
{
    public function actionIndex()
    {
        $plan = Yii::$app->request->get('plan');
        return $this->render('index', ['plan' => is_string($plan) && preg_match('/^[a-z]{1,20}$/', $plan) ? $plan : null]);
    }
}
