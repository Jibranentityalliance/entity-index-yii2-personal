<?php

/** @var yii\web\View $this */
/** @var array $st */
/** @var string $day */
/** @var string[] $days */
/** @var array $res */
/** @var array|null $stats */

use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Url;

$code = strtolower($st['code']);
$this->title = "New {$st['name']} businesses — LLCs & corporations filed today";
$this->params['description'] = "Daily list of new {$st['name']} LLCs and corporations from the {$st['name']} Secretary of State, searchable within 24 hours of filing.";
$this->params['canonical'] = '/new/' . $code;
$method = ['api' => 'Official open-data API', 'file' => 'Official daily bulk file', 'scrape' => 'State website', 'tbd' => 'State website'][$st['method']];
?>
<div class="container">
    <div class="page-head">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= Url::to(['/site/index']) ?>">Home</a><span>/</span><a href="<?= Url::to(['/new/index']) ?>">New filings</a><span>/</span><?= Html::encode($st['name']) ?></nav>
        <h1>New <?= Html::encode($st['name']) ?> businesses</h1>
        <p class="text-muted mb-0">Newly registered LLCs and corporations from the <?= Html::encode($st['name']) ?> Secretary of State.
            <a href="<?= Url::to(['/search/index', 'state' => $st['code']]) ?>">Search all <?= Html::encode($st['name']) ?> companies →</a></p>
    </div>
    <div class="row g-3 stats mb-4">
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= Fmt::num($stats['latestDayCount'] ?? 0) ?></div><div class="l">Latest filing day</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= Fmt::num($stats['last7Days'] ?? 0) ?></div><div class="l">Last 7 days</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= isset($stats['medianLagHours']) ? Fmt::lag($stats['medianLagHours']) : '—' ?></div><div class="l">Median filed → searchable</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="v fs-6 pt-2"><?= Html::encode($method) ?></div><div class="l">Last update <?= Fmt::dateTime($stats['lastIndexedAt'] ?? null) ?></div></div></div>
    </div>
    <?= $this->render('//new/_filings', ['basePath' => '/new/state', 'routeParams' => ['state' => $code], 'day' => $day, 'days' => $days, 'res' => $res, 'stateCode' => $st['code']]) ?>
</div>
