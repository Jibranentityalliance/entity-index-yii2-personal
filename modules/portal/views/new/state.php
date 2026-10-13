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
$this->title = 'New ' . $st['name'] . ' businesses';
$method = ['api' => 'Official open-data API', 'file' => 'Official daily bulk file', 'scrape' => 'State website', 'tbd' => 'State website'][$st['method']];
?>
<p class="text-muted">Newly registered LLCs and corporations from the <?= Html::encode($st['name']) ?> Secretary of State.
    <a href="<?= Url::to(['/portal/search/index', 'state' => $st['code']]) ?>">Search all <?= Html::encode($st['name']) ?> companies →</a> ·
    <a href="<?= Url::to(['/portal/new/index']) ?>">All states</a></p>
<div class="row">
    <div class="col-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-calendar-day"></i></span><div class="info-box-content"><span class="info-box-text">Latest filing day</span><span class="info-box-number"><?= Fmt::num($stats['latestDayCount'] ?? 0) ?></span></div></div></div>
    <div class="col-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-calendar-week"></i></span><div class="info-box-content"><span class="info-box-text">Last 7 days</span><span class="info-box-number"><?= Fmt::num($stats['last7Days'] ?? 0) ?></span></div></div></div>
    <div class="col-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-hourglass-half"></i></span><div class="info-box-content"><span class="info-box-text">Median filed → searchable</span><span class="info-box-number"><?= isset($stats['medianLagHours']) ? Fmt::lag($stats['medianLagHours']) : '—' ?></span></div></div></div>
    <div class="col-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-secondary"><i class="fas fa-database"></i></span><div class="info-box-content"><span class="info-box-text"><?= Html::encode($method) ?></span><span class="info-box-text text-muted">Updated <?= Fmt::dateTime($stats['lastIndexedAt'] ?? null) ?></span></div></div></div>
</div>
<?= $this->render('/new/_filings', ['basePath' => '/portal/new/state', 'routeParams' => ['state' => $code], 'day' => $day, 'days' => $days, 'res' => $res, 'stateCode' => $st['code']]) ?>
