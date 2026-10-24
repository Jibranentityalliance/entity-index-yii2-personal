<?php

/** @var yii\web\View $this */
/** @var array $stats per-state counters (EntityRepository::stateStats) */
/** @var array $states rows of States::all() to list, in order */
/** @var array<string,int>|null $counts companies filed per state in a chosen date range (replaces Last 7 days) */
/** @var string|null $countsLabel header for $counts */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;

$counts = $counts ?? null;
?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>Jurisdiction</th><th class="d-none d-md-table-cell">Source</th><th class="text-right">Last 24h</th>
                    <th class="text-right"><?= $counts === null ? 'Last 7 days' : Html::encode($countsLabel) ?></th>
                    <th class="text-right">Median lag</th><th class="d-none d-md-table-cell">Last update</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($states as $st):
                    $s = $stats[$st['code']] ?? null;
                    $lag = $s['medianLagHours'] ?? null;
                    [$label, $cls] = States::SOURCES[States::source($st)];
                    [$health, $hcls] = States::HEALTH[States::health($s)]; ?>
                    <tr>
                        <td><a class="font-weight-bold" href="<?= Fmt::stateUrl($st['code']) ?>"><?= Html::encode($st['name']) ?></a></td>
                        <td class="d-none d-md-table-cell"><span class="badge badge-<?= $cls ?>"><?= $label ?></span></td>
                        <td class="text-right"><?= Fmt::num($s['last24h'] ?? 0) ?></td>
                        <td class="text-right"><?= Fmt::num($counts === null ? ($s['last7Days'] ?? 0) : ($counts[$st['code']] ?? 0)) ?></td>
                        <td class="text-right"><?= $lag !== null ? Fmt::lag($lag) : '—' ?></td>
                        <td class="d-none d-md-table-cell text-muted small"><?= Fmt::dateTime($s['lastIndexedAt'] ?? null) ?></td>
                        <td><span class="badge badge-<?= $hcls ?>"><?= $health ?></span></td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$states): ?>
                    <tr><td colspan="7" class="text-center text-muted"><?= Html::encode($empty ?? 'No filings in the last 7 days.') ?></td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer text-muted small">Last 24h = companies that became searchable here in the last 24 hours. Median lag = median hours between the timestamp on the state record and the moment the record became searchable here, over the last 7 days.</div>
</div>
