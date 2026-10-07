<?php

/** @var yii\web\View $this */
/** @var app\modules\portal\models\AccountForm $model */
/** @var app\models\User $user */

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Account';
?>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Profile &amp; password</h3></div>
            <?php $form = ActiveForm::begin([
                'id' => 'account-form',
                'enableClientValidation' => false,
                'fieldConfig' => [
                    'options' => ['class' => 'form-group'],
                    'template' => "{label}\n{input}\n{error}",
                    'inputOptions' => ['class' => 'form-control'],
                    'errorOptions' => ['class' => 'invalid-feedback d-block'],
                ],
            ]) ?>
            <div class="card-body">
                <div class="form-group"><label>Email</label><input class="form-control" value="<?= Html::encode($user->email) ?>" disabled></div>
                <?= $form->field($model, 'name')->textInput(['autocomplete' => 'name']) ?>
                <hr>
                <p class="text-muted small">Leave the password fields empty to keep your current password.</p>
                <?= $form->field($model, 'current_password')->passwordInput(['autocomplete' => 'current-password']) ?>
                <?= $form->field($model, 'new_password')->passwordInput(['autocomplete' => 'new-password']) ?>
                <?= $form->field($model, 'new_password_repeat')->passwordInput(['autocomplete' => 'new-password']) ?>
            </div>
            <div class="card-footer"><?= Html::submitButton('Save changes', ['class' => 'btn btn-primary']) ?></div>
            <?php ActiveForm::end() ?>
        </div>
    </div>
</div>
