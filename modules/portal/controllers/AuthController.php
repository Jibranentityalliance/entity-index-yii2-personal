<?php

namespace app\modules\portal\controllers;

use app\models\User;
use app\modules\portal\models\ForgotPasswordForm;
use app\modules\portal\models\LoginForm;
use app\modules\portal\models\RegisterForm;
use app\modules\portal\models\ResetPasswordForm;
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
                    ['allow' => true, 'actions' => ['login', 'register', 'forgot', 'reset'], 'roles' => ['?']],
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

    public function actionForgot()
    {
        $model = new ForgotPasswordForm();
        if ($model->load(Yii::$app->request->post()) && $model->send()) {
            return $this->render('forgot-sent', ['email' => $model->email]);
        }
        return $this->render('forgot', ['model' => $model]);
    }

    public function actionReset(string $token = '')
    {
        $this->view->registerMetaTag(['name' => 'referrer', 'content' => 'no-referrer'], 'referrer');
        $user = User::findByPasswordResetToken($token);
        if ($user === null) {
            return $this->render('reset-invalid');
        }
        $model = new ResetPasswordForm($user);
        if ($model->load(Yii::$app->request->post()) && $model->reset()) {
            Yii::$app->session->setFlash('success', 'Your password has been changed. Sign in with your new password.');
            return $this->redirect(['/portal/auth/login']);
        }
        $model->password = $model->password_repeat = '';
        return $this->render('reset', ['model' => $model, 'email' => $user->email]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->redirect(['/site/index']);
    }
}
