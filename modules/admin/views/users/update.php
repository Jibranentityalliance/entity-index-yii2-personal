<?php

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var app\modules\admin\models\UserForm $model */
/** @var bool $self */
/** @var array $defaults */
/** @var array[] $usage */

use app\helpers\Fmt;
use app\modules\admin\models\UserForm;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = $user->email;
$default = function (string $group, string $key) use ($defaults): string {
    if (!array_key_exists($key, $defaults[$group] ?? [])) {
        return 'Not set';
    }
    $v = $defaults[$group][$key];
    return 'Plan default: ' . ($v === null ? 'unlimited' : number_format((int) $v));
};
?>
<p><a href="<?= Url::to(['/admin/users/index']) ?>">&larr; All users</a></p>
<div class="row">
    <div class="col-lg-7">
        <?php $form = ActiveForm::begin([
            'id' => 'user-form',
            'enableClientValidation' => false,
            'fieldConfig' => [
                'options' => ['class' => 'form-group'],
                'template' => "{label}\n{input}\n{error}",
                'inputOptions' => ['class' => 'form-control'],
                'errorOptions' => ['class' => 'invalid-feedback d-block'],
            ],
        ]) ?>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Account</h3></div>
            <div class="card-body">
                <?php if ($model->hasErrors()): ?>
                    <div class="alert alert-danger"><?= implode('<br>', array_map([Html::class, 'encode'], $model->getErrorSummary(true))) ?></div>
                <?php endif ?>
                <div class="form-group"><label>Email</label><input class="form-control" value="<?= Html::encode($user->email) ?>" disabled></div>
                <?= $form->field($model, 'name')->textInput() ?>
                <div class="form-row">
                    <div class="col-md-4"><?= $form->field($model, 'plan')->dropDownList(UserForm::PLANS) ?></div>
                    <div class="col-md-4"><?= $form->field($model, 'role')->dropDownList(UserForm::ROLES, ['disabled' => $self]) ?></div>
                    <div class="col-md-4"><?= $form->field($model, 'status')->dropDownList(UserForm::STATUSES, ['disabled' => $self]) ?></div>
                </div>
                <?php if ($self): ?><p class="text-muted small mb-0">You cannot change your own role or disable your own account.</p><?php endif ?>
                <?php if ($self): ?>
                    <input type="hidden" name="UserForm[role]" value="<?= Html::encode($user->role) ?>">
                    <input type="hidden" name="UserForm[status]" value="<?= (int) $user->status ?>">
                <?php endif ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Limits for this account</h3></div>
            <div class="card-body">
                <p class="text-muted">Leave a field empty to use the plan default. A number replaces the default for this account only. Limits only apply where the plan includes the feature; to give API access, change the plan.</p>
                <?php foreach (UserForm::LIMITS as $group => $def): ?>
                    <h4 class="h6 mt-3"><?= Html::encode($def['title']) ?></h4>
                    <div class="form-row">
                        <?php foreach ($def['fields'] as $key => $label): ?>
                            <div class="col-md-4 form-group">
                                <label for="lim-<?= $group ?>-<?= $key ?>"><?= Html::encode($label) ?></label>
                                <input id="lim-<?= $group ?>-<?= $key ?>" class="form-control" name="UserForm[limits][<?= $group ?>][<?= $key ?>]" value="<?= Html::encode($model->limitValue($group, $key)) ?>" placeholder="<?= Html::encode($default($group, $key)) ?>" autocomplete="off">
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endforeach ?>
                <p class="text-muted small mb-0">CSV export "Rows a month" also accepts <code>unlimited</code>.</p>
            </div>
            <div class="card-footer"><?= Html::submitButton('Save changes', ['class' => 'btn btn-primary']) ?></div>
        </div>
        <?php ActiveForm::end() ?>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Details</h3></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <tbody>
                    <tr><th class="w-50">Joined</th><td><?= Html::encode(Fmt::dateTime($user->created_at)) ?></td></tr>
                    <tr><th>Last sign-in</th><td><?= Html::encode($user->last_login_at ? Fmt::dateTime($user->last_login_at) : 'Never') ?></td></tr>
                    <tr><th>Updated</th><td><?= Html::encode(Fmt::dateTime($user->updated_at)) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Usage</h3></div>
            <div class="card-body p-0">
                <?php if (!$usage): ?>
                    <div class="p-3 text-muted">This plan has no metered features.</div>
                <?php else: ?>
                    <table class="table mb-0">
                        <thead><tr><th>Measure</th><th class="text-right">Used</th><th class="text-right">Limit</th></tr></thead>
                        <tbody>
                        <?php foreach ($usage as [$label, $used, $limit]): ?>
                            <tr><td><?= Html::encode($label) ?></td><td class="text-right"><?= Html::encode($used) ?></td><td class="text-right"><?= Html::encode($limit) ?></td></tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                <?php endif ?>
            </div>
            <div class="card-footer">
                <?= Html::beginForm(['/admin/users/reset-usage', 'id' => $user->id], 'post', ['onsubmit' => "return confirm('Reset this month\'s usage and today\'s lookups for this account?')"]) ?>
                <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-undo"></i> Reset this period's usage</button>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
</div>
