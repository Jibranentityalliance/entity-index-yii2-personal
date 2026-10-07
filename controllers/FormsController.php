<?php

namespace app\controllers;

use app\models\OptOutRequest;
use app\models\Waitlist;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/** JSON endpoints for the two public forms. POST only; CSRF-protected (yii.js sends the token on AJAX). */
class FormsController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['waitlist' => ['post'], 'opt-out' => ['post']]],
        ];
    }

    public function beforeAction($action)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return parent::beforeAction($action);
    }

    public function actionWaitlist()
    {
        $m = new Waitlist();
        $m->load(Yii::$app->request->post(), '');
        if ($m->website !== null && $m->website !== '') {
            return ['ok' => true]; // honeypot: pretend success, store nothing
        }
        if (!$m->subscribe()) {
            Yii::$app->response->statusCode = 400;
            return ['ok' => false, 'error' => $m->getFirstError('email') ?: 'Something went wrong.'];
        }
        return ['ok' => true];
    }

    public function actionOptOut()
    {
        $m = new OptOutRequest();
        $m->load(Yii::$app->request->post(), '');
        if (!$m->save()) {
            Yii::$app->response->statusCode = 400;
            $errors = $m->getFirstErrors();
            return ['ok' => false, 'error' => reset($errors) ?: 'Please check the form.'];
        }
        return ['ok' => true];
    }
}
