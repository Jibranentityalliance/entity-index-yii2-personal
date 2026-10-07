<?php

/** @var yii\web\View $this */
/** @var string $day */
/** @var string[] $days */
/** @var array $res */

$this->title = 'New business filings today — all 50 states';
$this->params['description'] = 'Every new LLC and corporation filed with US Secretaries of State, updated daily. Browse the latest filings by state.';
$this->params['canonical'] = '/new';
?>
<div class="container">
    <div class="page-head">
        <h1>New business filings</h1>
        <p class="text-muted mb-0">New LLCs, corporations and partnerships from all 50 states and DC, as they’re filed.</p>
    </div>
    <?= $this->render('_filings', ['basePath' => '/new/index', 'routeParams' => [], 'day' => $day, 'days' => $days, 'res' => $res]) ?>
</div>
