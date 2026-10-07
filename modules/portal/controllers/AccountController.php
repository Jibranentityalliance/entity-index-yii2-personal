<?php

namespace app\modules\portal\controllers;

use app\modules\portal\models\AccountForm;
use Yii;

class AccountController extends PortalController
{
    public function actionIndex()
    {
        $model = new AccountForm(Yii::$app->user->identity);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // A new password rotates the auth key; log in again so this session stays valid.
            Yii::$app->user->login(Yii::$app->user->identity, 3600 * 24 * 14);
            Yii::$app->session->setFlash('success', 'Account updated.');
            return $this->refresh();
        }
        $model->current_password = $model->new_password = $model->new_password_repeat = '';
        return $this->render('index', ['model' => $model, 'user' => Yii::$app->user->identity]);
    }
}
