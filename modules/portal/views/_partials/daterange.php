<?php

/** @var yii\web\View $this */
/** @var string $id */
/** @var string|null $from YYYY-MM-DD */
/** @var string|null $to YYYY-MM-DD */
/** @var string $class column classes */

use app\modules\portal\assets\DateRangeAsset;
use yii\helpers\Html;

DateRangeAsset::register($this);
?>
<div class="<?= $class ?> ei-daterange">
    <label for="<?= $id ?>" class="sr-only">Filing date</label>
    <i class="far fa-calendar ei-daterange-icon" aria-hidden="true"></i>
    <input id="<?= $id ?>" class="form-control" type="text" value="<?= Html::encode(implode(' to ', array_filter([$from, $to]))) ?>" placeholder="Filing date" autocomplete="off" data-ei-daterange>
    <input type="hidden" name="from" value="<?= Html::encode($from) ?>">
    <input type="hidden" name="to" value="<?= Html::encode($to) ?>">
</div>
