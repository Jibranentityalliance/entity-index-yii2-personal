<?php

/** @var yii\web\View $this */
/** @var app\modules\portal\models\RegisterForm $model */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = 'Create account';
?>
<p class="login-box-msg">Create a free EntityIndex account</p>
<?php $form = ActiveForm::begin([
    'id' => 'register-form',
    'enableClientValidation' => false,
    'fieldConfig' => [
        'options' => ['class' => 'mb-3'],
        'template' => "{input}\n{error}",
        'inputOptions' => ['class' => 'form-control'],
        'errorOptions' => ['class' => 'invalid-feedback d-block'],
    ],
]) ?>
<?= $form->field($model, 'name')->textInput(['placeholder' => 'Full name', 'autofocus' => true, 'autocomplete' => 'name']) ?>
<?= $form->field($model, 'email')->input('email', ['placeholder' => 'Email', 'autocomplete' => 'username']) ?>
<?= $form->field($model, 'password')->passwordInput(['placeholder' => 'Password (8+ characters)', 'autocomplete' => 'new-password']) ?>
<?= $form->field($model, 'password_repeat')->passwordInput(['placeholder' => 'Confirm password', 'autocomplete' => 'new-password']) ?>
<?= Html::submitButton('Create account', ['class' => 'btn btn-primary btn-block']) ?>
<?php ActiveForm::end() ?>
<p class="mt-3 mb-0"><a href="<?= Url::to(['/portal/auth/login']) ?>">I already have an account</a></p>
