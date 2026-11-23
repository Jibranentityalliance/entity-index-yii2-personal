<?php

namespace app\controllers;

use app\models\ContactMessage;
use app\models\OptOutRequest;
use app\models\Waitlist;
use app\services\Mail;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/** JSON endpoints for the two public forms. POST only; CSRF-protected (yii.js sends the token on AJAX). */
class FormsController extends Controller
{
    private const OPT_OUT_PER_HOUR = 10;

    public function behaviors()
    {
        return [
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['waitlist' => ['post'], 'opt-out' => ['post'], 'contact' => ['post']]],
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

    public function actionContact()
    {
        $m = new ContactMessage();
        $m->load(Yii::$app->request->post(), '');
        if ($m->website !== null && $m->website !== '') {
            return ['ok' => true]; // honeypot
        }
        if (!$m->save()) {
            Yii::$app->response->statusCode = 400;
            $errors = $m->getFirstErrors();
            return ['ok' => false, 'error' => reset($errors) ?: 'Please check the form.'];
        }
        return ['ok' => true];
    }

    public function actionOptOut()
    {
        $key = 'forms.opt-out.' . md5((string) Yii::$app->request->userIP);
        $count = (int) Yii::$app->cache->get($key);
        if ($count >= self::OPT_OUT_PER_HOUR) {
            Yii::$app->response->statusCode = 429;
            return ['ok' => false, 'error' => 'Too many requests from your connection. Try again in an hour, or use the contact page.'];
        }
        $m = new OptOutRequest();
        $m->load(Yii::$app->request->post(), '');
        if (!$m->save()) {
            Yii::$app->response->statusCode = 400;
            $errors = $m->getFirstErrors();
            return ['ok' => false, 'error' => reset($errors) ?: 'Please check the form.'];
        }
        Yii::$app->cache->set($key, $count + 1, 3600);
        Mail::optOutReceived($m);
        Mail::optOutForAdmin($m);
        return ['ok' => true];
    }
}
