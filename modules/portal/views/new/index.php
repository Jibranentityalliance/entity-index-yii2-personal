<?php

/** @var yii\web\View $this */
/** @var string $day */
/** @var string[] $days */
/** @var array $res */

$this->title = 'New filings';
?>
<p class="text-muted">New LLCs, corporations and partnerships from all 50 states and DC, as they’re filed.</p>
<?= $this->render('_filings', ['basePath' => '/portal/new/index', 'routeParams' => [], 'day' => $day, 'days' => $days, 'res' => $res]) ?>
