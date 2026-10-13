<?php

/** @var yii\web\View $this */
/** @var array $stats */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;

$this->title = 'Coverage & freshness';
$method = ['api' => ['Open-data API', 'success'], 'file' => ['Bulk file', 'success'], 'scrape' => ['Website', 'secondary'], 'tbd' => ['Website', 'secondary']];
$live = count(array_intersect_key($stats, States::all()));
$within24 = count(array_filter($stats, fn($s) => ($s['medianLagHours'] ?? 99) <= 24));
?>
<p class="text-muted">Every state’s new filings come from official sources: open-data APIs and bulk files where they exist, the state website where they don’t.
    <span class="badge badge-success"><?= $live ?> of 51 jurisdictions live</span> <span class="badge badge-success"><?= $within24 ?> with median lag ≤ 24h</span></p>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover ei-data mb-0">
                <thead><tr>
                    <th>Jurisdiction</th><th class="d-none d-md-table-cell">Source</th><th class="text-right">Latest day</th><th class="text-right">Last 7 days</th>
                    <th class="text-right">Median lag</th><th class="d-none d-md-table-cell">Last update</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php foreach (States::all() as $st):
                    $s = $stats[$st['code']] ?? null;
                    $lag = $s['medianLagHours'] ?? null;
                    [$label, $cls] = $method[$st['method']];
                    $health = !$s ? ['Not live', 'danger'] : ($lag !== null && $lag <= 24 ? ['On time', 'success'] : ['Delayed', 'warning']); ?>
                    <tr>
                        <td><a class="font-weight-bold" href="<?= Fmt::stateUrl($st['code']) ?>"><?= Html::encode($st['name']) ?></a></td>
                        <td class="d-none d-md-table-cell"><span class="badge badge-<?= $cls ?>"><?= $label ?></span></td>
                        <td class="text-right"><?= Fmt::num($s['latestDayCount'] ?? 0) ?></td>
                        <td class="text-right"><?= Fmt::num($s['last7Days'] ?? 0) ?></td>
                        <td class="text-right"><?= $lag !== null ? Fmt::lag($lag) : '—' ?></td>
                        <td class="d-none d-md-table-cell text-muted small"><?= Fmt::dateTime($s['lastIndexedAt'] ?? null) ?></td>
                        <td><span class="badge badge-<?= $health[1] ?>"><?= $health[0] ?></span></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer text-muted small">Median lag = median hours between the timestamp on the state record and the moment the record became searchable here, over the last 7 days.</div>
</div>
