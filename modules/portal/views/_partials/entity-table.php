<?php

/** @var yii\web\View $this */
/** @var array[] $items rows from EntityRepository (summary columns) */
/** @var bool $showState */
/** @var bool $showCounty */
/** @var string|null $lockBefore YYYY-MM-DD: filings before this day are locked on the free plan */
/** @var string $empty */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;

$showState = $showState ?? true;
$showCounty = $showCounty ?? true;
$lockBefore = $lockBefore ?? null;
$empty = $empty ?? 'No companies match.';
?>
<?php if (!$items): ?>
    <div class="p-4 text-center text-muted"><?= Html::encode($empty) ?></div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Company</th>
                <?php if ($showState): ?><th>State</th><?php endif ?>
                <th class="d-none d-md-table-cell">Type</th>
                <?php if ($showCounty): ?><th class="d-none d-md-table-cell">County</th><?php endif ?>
                <th>Filed</th>
                <th class="text-right d-none d-md-table-cell" title="Time from state filing to searchable on EntityIndex">Indexed after</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $e): ?>
                <tr>
                    <td>
                        <a class="font-weight-bold" href="<?= Fmt::entityUrl($e) ?>"><?= Html::encode(Fmt::na($e['name'])) ?></a>
                        <?php if ($lockBefore !== null && ($e['filed_at'] === null || substr($e['filed_at'], 0, 10) < $lockBefore)): ?><i class="fas fa-lock text-muted ml-1" title="Older than your plan covers" aria-label="Older than your plan covers"></i><?php endif ?>
                        <?php if ($e['status'] !== 'Active' && $e['status'] !== ''): ?> <?= Fmt::statusBadgeBs4($e['status']) ?><?php endif ?>
                    </td>
                    <?php if ($showState): ?>
                        <td><?php if ($e['state'] === 'NA'): ?>N/A<?php else: ?><a href="<?= Fmt::stateUrl($e['state']) ?>" title="<?= Html::encode(States::name($e['state'])) ?>"><?= Html::encode($e['state']) ?></a><?php endif ?></td>
                    <?php endif ?>
                    <td class="d-none d-md-table-cell text-muted"><?= Html::encode(Fmt::na($e['entity_type'])) ?></td>
                    <?php if ($showCounty): ?><td class="d-none d-md-table-cell text-muted"><?= Html::encode(Fmt::na($e['county'])) ?></td><?php endif ?>
                    <td class="text-nowrap"><?= $e['filed_at'] ? Fmt::date($e['filed_at']) : 'N/A' ?></td>
                    <td class="text-right d-none d-md-table-cell text-muted"><?= $e['filed_at'] ? Fmt::lag(Fmt::lagHours($e['filed_at'], $e['indexed_at'])) : 'N/A' ?></td>
                    <td class="text-right"><a href="<?= Fmt::entityUrl($e) ?>" tabindex="-1" aria-hidden="true">View →</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
