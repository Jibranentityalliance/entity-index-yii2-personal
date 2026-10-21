<?php

/** @var yii\web\View $this */
/** @var array[] $items rows from EntityRepository (summary columns) */
/** @var bool $showState */
/** @var string $empty */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;

$showState = $showState ?? true;
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
                <th class="d-none d-md-table-cell">County</th>
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
                        <?php if ($e['status'] !== 'Active' && $e['status'] !== ''): ?> <?= Fmt::statusBadgeBs4($e['status']) ?><?php endif ?>
                    </td>
                    <?php if ($showState): ?>
                        <td><?php if ($e['state'] === 'NA'): ?>N/A<?php else: ?><a href="<?= Fmt::stateUrl($e['state']) ?>" title="<?= Html::encode(States::name($e['state'])) ?>"><?= Html::encode($e['state']) ?></a><?php endif ?></td>
                    <?php endif ?>
                    <td class="d-none d-md-table-cell text-muted"><?= Html::encode(Fmt::na($e['entity_type'])) ?></td>
                    <td class="d-none d-md-table-cell text-muted"><?= Html::encode(Fmt::na($e['county'])) ?></td>
                    <td class="text-nowrap"><?= $e['filed_at'] ? Fmt::date($e['filed_at']) : 'N/A' ?></td>
                    <td class="text-right d-none d-md-table-cell text-muted"><?= $e['filed_at'] ? Fmt::lag(Fmt::lagHours($e['filed_at'], $e['indexed_at'])) : 'N/A' ?></td>
                    <td class="text-right"><a href="<?= Fmt::entityUrl($e) ?>" tabindex="-1" aria-hidden="true">View →</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
