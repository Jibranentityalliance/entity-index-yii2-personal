<?php

namespace app\modules\portal\controllers;

use app\modules\portal\models\LoginForm;
use app\modules\portal\models\RegisterForm;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

class AuthController extends Controller
{
    public $layout = 'auth';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'actions' => ['login', 'register'], 'roles' => ['?']],
                    ['allow' => true, 'actions' => ['logout'], 'roles' => ['@']],
                ],
                'denyCallback' => fn() => $this->redirect(['/portal/default/index']),
            ],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['logout' => ['post']]],
        ];
    }

    public function actionLogin()
    {
        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack(['/portal/default/index']);
        }
        $model->password = '';
        return $this->render('login', ['model' => $model]);
    }

    public function actionRegister()
    {
        $model = new RegisterForm();
        if ($model->load(Yii::$app->request->post()) && ($user = $model->register())) {
            Yii::$app->user->login($user, 3600 * 24 * 14);
            Yii::$app->session->setFlash('success', 'Welcome to EntityIndex! Your free account is ready.');
            return $this->redirect(['/portal/default/index']);
        }
        $model->password = $model->password_repeat = '';
        return $this->render('register', ['model' => $model]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->redirect(['/site/index']);
    }
}
