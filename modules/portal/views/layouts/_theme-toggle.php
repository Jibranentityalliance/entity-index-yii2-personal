<?php

/** @var yii\web\View $this */
/** @var bool $light */
/** @var string $class */

$label = $light ? 'Switch to dark mode' : 'Switch to light mode';
?>
<button type="button" class="btn ei-theme-toggle <?= $class ?? '' ?>" data-ei-theme-toggle aria-label="<?= $label ?>" title="<?= $label ?>"><i class="fas <?= $light ? 'fa-moon' : 'fa-sun' ?>"></i></button>
