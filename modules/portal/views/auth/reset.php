<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = 'Choose a new password';
?>
<p class="login-box-msg">Choose a new password for <strong><?= Html::encode($email) ?></strong></p>
<?php $form = ActiveForm::begin([
    'id' => 'reset-form',
    'enableClientValidation' => false,
    'fieldConfig' => [
        'options' => ['class' => 'mb-3'],
        'template' => "{input}\n{error}",
        'inputOptions' => ['class' => 'form-control'],
        'errorOptions' => ['class' => 'invalid-feedback d-block'],
    ],
]) ?>
<input type="email" name="username" value="<?= Html::encode($email) ?>" autocomplete="username" hidden>
<?= $form->field($model, 'password')->passwordInput(['placeholder' => 'New password (8+ characters)', 'autofocus' => true, 'autocomplete' => 'new-password']) ?>
<?= $form->field($model, 'password_repeat')->passwordInput(['placeholder' => 'Confirm new password', 'autocomplete' => 'new-password']) ?>
<?= Html::submitButton('Change password', ['class' => 'btn btn-primary btn-block']) ?>
<?php ActiveForm::end() ?>
<p class="mt-3 mb-0 text-muted small">Changing your password signs you out on every other device.</p>
