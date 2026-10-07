<?php

/** @var yii\web\View $this */
/** @var app\modules\portal\models\LoginForm $model */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = 'Sign in';
?>
<p class="login-box-msg">Sign in to your EntityIndex account</p>
<?php $form = ActiveForm::begin([
    'id' => 'login-form',
    'enableClientValidation' => false,
    'fieldConfig' => [
        'options' => ['class' => 'mb-3'],
        'template' => "{input}\n{error}",
        'inputOptions' => ['class' => 'form-control'],
        'errorOptions' => ['class' => 'invalid-feedback d-block'],
    ],
]) ?>
<?= $form->field($model, 'email')->input('email', ['placeholder' => 'Email', 'autofocus' => true, 'autocomplete' => 'username']) ?>
<?= $form->field($model, 'password')->passwordInput(['placeholder' => 'Password', 'autocomplete' => 'current-password']) ?>
<div class="row">
    <div class="col-7">
        <div class="icheck-primary">
            <?= Html::activeCheckbox($model, 'rememberMe', ['id' => 'remember']) ?>
        </div>
    </div>
    <div class="col-5"><?= Html::submitButton('Sign in', ['class' => 'btn btn-primary btn-block']) ?></div>
</div>
<?php ActiveForm::end() ?>
<p class="mt-3 mb-0"><a href="<?= Url::to(['/portal/auth/register']) ?>" class="text-center">Create a free account</a></p>
