<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = 'Reset password';
?>
<p class="login-box-msg">Enter the email you signed up with and we will send you a link to choose a new password.</p>
<?php $form = ActiveForm::begin([
    'id' => 'forgot-form',
    'enableClientValidation' => false,
    'fieldConfig' => [
        'options' => ['class' => 'mb-3'],
        'template' => "{input}\n{error}",
        'inputOptions' => ['class' => 'form-control'],
        'errorOptions' => ['class' => 'invalid-feedback d-block'],
    ],
]) ?>
<?= $form->field($model, 'email')->input('email', ['placeholder' => 'Email', 'autofocus' => true, 'autocomplete' => 'username']) ?>
<?= Html::submitButton('Send reset link', ['class' => 'btn btn-primary btn-block']) ?>
<?php ActiveForm::end() ?>
<p class="mt-3 mb-0"><a href="<?= Url::to(['/portal/auth/login']) ?>">Back to sign in</a></p>
