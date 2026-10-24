<?php

/** @var yii\web\View $this */
/** @var array $stats */
/** @var array{companies:int,states:int,addresses:int} $week */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Dashboard';
// Top 10 states by companies filed in the last 7 days.
$top = array_filter(States::all(), fn($s) => ($stats[$s['code']]['last7Days'] ?? 0) > 0);
uasort($top, fn($a, $b) => $stats[$b['code']]['last7Days'] <=> $stats[$a['code']]['last7Days']);
$top = array_slice($top, 0, 10);
?>
<h2 class="h5 mb-3">Last 7 days</h2>
<div class="row">
    <?php foreach ([
        ['fa-building', 'Total companies', Fmt::num($week['companies']), ['/portal/new/index']],
        ['fa-map-marked-alt', 'States', Fmt::num($week['states']), ['/portal/states/index']],
        ['fa-map-marker-alt', 'Structured addresses', Fmt::num($week['addresses']), null],
    ] as [$icon, $label, $value, $route]): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="info-box">
                <span class="info-box-icon"><i class="fas <?= $icon ?>"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?php if ($route): ?><a href="<?= Url::to($route) ?>" class="text-reset stretched-link"><?= Html::encode($label) ?></a><?php else: ?><?= Html::encode($label) ?><?php endif ?></span>
                    <span class="info-box-number"><?= Html::encode($value) ?></span>
                </div>
            </div>
        </div>
    <?php endforeach ?>
    <div class="col-sm-6 col-lg-3">
        <div class="info-box ei-info-box-placeholder">
            <span class="info-box-icon"><i class="fas fa-plus"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-muted">More metrics</span>
                <span class="info-box-number text-muted">Coming soon</span>
            </div>
        </div>
    </div>
</div>
<p class="text-muted small mt-n2">Structured addresses: agent and officer addresses with street, city, state and ZIP each available.</p>

<div class="d-flex align-items-baseline justify-content-between mt-2 mb-3">
    <h2 class="h5 mb-0">Top 10 states · last 7 days</h2>
    <a href="<?= Url::to(['/portal/states/index']) ?>">All states →</a>
</div>
<?= $this->render('/states/_table', ['stats' => $stats, 'states' => $top]) ?>
