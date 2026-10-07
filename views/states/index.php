<?php

/** @var yii\web\View $this */
/** @var array $stats */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;

$this->title = 'Coverage & freshness by state';
$this->params['description'] = 'Live coverage and freshness for all 51 US jurisdictions: filings per day and median time from state filing to searchable.';
$this->params['canonical'] = '/states';
$method = ['api' => ['Open-data API', 'ok'], 'file' => ['Bulk file', 'ok'], 'scrape' => ['Website', ''], 'tbd' => ['Website', '']];
$live = count(array_intersect_key($stats, States::all()));
$within24 = count(array_filter($stats, fn($s) => ($s['medianLagHours'] ?? 99) <= 24));
?>
<div class="container">
    <div class="page-head">
        <h1>Coverage &amp; freshness</h1>
        <p class="text-muted" style="max-width:70ch">We pull every state’s new filings from official sources: open-data APIs and bulk files where they exist, the state website where they don’t. Every record keeps its state filing number and the time we pulled it.</p>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge-pill-ei ok"><span class="dot"></span><?= $live ?> of 51 jurisdictions live</span>
            <span class="badge-pill-ei ok"><span class="dot"></span><?= $within24 ?> with median lag ≤ 24h</span>
        </div>
    </div>
    <div class="card-ei flush">
        <div class="table-responsive">
            <table class="table data align-middle mb-0">
                <thead><tr>
                    <th>Jurisdiction</th><th class="d-none d-md-table-cell">Source</th><th class="text-end">Latest day</th><th class="text-end">Last 7 days</th>
                    <th class="text-end">Median lag</th><th class="d-none d-md-table-cell">Last update</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php foreach (States::all() as $st):
                    $s = $stats[$st['code']] ?? null;
                    $lag = $s['medianLagHours'] ?? null;
                    [$label, $cls] = $method[$st['method']];
                    $health = !$s ? ['Not live', 'bad'] : ($lag !== null && $lag <= 24 ? ['On time', 'ok'] : ['Delayed', 'warn']); ?>
                    <tr>
                        <td class="name"><a href="<?= Fmt::stateUrl($st['code']) ?>"><?= Html::encode($st['name']) ?></a></td>
                        <td class="d-none d-md-table-cell"><span class="badge-pill-ei <?= $cls ?>"><?= $label ?></span></td>
                        <td class="text-end"><?= Fmt::num($s['latestDayCount'] ?? 0) ?></td>
                        <td class="text-end"><?= Fmt::num($s['last7Days'] ?? 0) ?></td>
                        <td class="text-end"><?= $lag !== null ? Fmt::lag($lag) : '—' ?></td>
                        <td class="d-none d-md-table-cell text-muted small"><?= Fmt::dateTime($s['lastIndexedAt'] ?? null) ?></td>
                        <td><span class="badge-pill-ei <?= $health[1] ?>"><span class="dot"></span><?= $health[0] ?></span></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-muted small mt-3">Median lag = median hours between the timestamp on the state record and the moment the record became searchable here, over the last 7 days.</p>
</div>
