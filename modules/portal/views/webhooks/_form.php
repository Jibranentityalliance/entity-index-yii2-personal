<?php

use app\helpers\States;
use app\modules\portal\assets\MultiSelectAsset;
use yii\helpers\Html;

$states = array_map(fn($s) => $s['name'], States::all());
asort($states);
MultiSelectAsset::register($this);
?>
<div class="form-group">
    <?= Html::activeLabel($model, 'url') ?>
    <?= Html::activeInput('url', $model, 'url', ['class' => 'form-control' . ($model->hasErrors('url') ? ' is-invalid' : ''), 'placeholder' => 'https://example.com/hooks/entityindex', 'required' => true, 'maxlength' => 2048]) ?>
    <?= Html::error($model, 'url', ['class' => 'invalid-feedback d-block']) ?>
</div>
<div class="form-group">
    <?= Html::activeLabel($model, 'description') ?> <span class="text-muted small">(optional)</span>
    <?= Html::activeTextInput($model, 'description', ['class' => 'form-control', 'maxlength' => 100, 'placeholder' => 'e.g. CRM import']) ?>
</div>
<div class="form-row">
    <div class="form-group col-md-6">
        <?= Html::activeLabel($model, 'stateList') ?>
        <?= Html::activeListBox($model, 'stateList', $states, ['multiple' => true, 'unselect' => null, 'data-ei-multiselect' => true, 'data-placeholder' => 'All states']) ?>
        <small class="form-text text-muted">Leave empty for every state. Type to search.</small>
    </div>
    <div class="form-group col-md-6">
        <?= Html::activeLabel($model, 'typeList') ?>
        <?= Html::activeListBox($model, 'typeList', array_combine(States::ENTITY_TYPES, States::ENTITY_TYPES), ['multiple' => true, 'unselect' => null, 'data-ei-multiselect' => true, 'data-placeholder' => 'All entity types']) ?>
        <small class="form-text text-muted">Leave empty for every type.</small>
    </div>
</div>
