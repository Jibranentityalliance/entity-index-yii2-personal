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
    <div class="empty"><?= Html::encode($empty) ?></div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table data align-middle mb-0">
            <thead>
            <tr>
                <th>Company</th>
                <?php if ($showState): ?><th>State</th><?php endif ?>
                <th class="d-none d-md-table-cell">Type</th>
                <th class="d-none d-md-table-cell">County</th>
                <th>Filed</th>
                <th class="text-end d-none d-md-table-cell" title="Time from state filing to searchable on EntityIndex">Indexed after</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $e): ?>
                <tr>
                    <td class="name">
                        <a href="<?= Fmt::entityUrl($e) ?>"><?= Html::encode($e['name']) ?></a>
                        <?php if ($e['status'] !== 'Active'): ?> <?= Fmt::statusBadge($e['status']) ?><?php endif ?>
                    </td>
                    <?php if ($showState): ?>
                        <td><a href="<?= Fmt::stateUrl($e['state']) ?>" title="<?= Html::encode(States::name($e['state'])) ?>"><?= Html::encode($e['state']) ?></a></td>
                    <?php endif ?>
                    <td class="d-none d-md-table-cell text-muted"><?= Html::encode($e['entity_type']) ?></td>
                    <td class="d-none d-md-table-cell text-muted"><?= Html::encode($e['county'] ?? '—') ?></td>
                    <td class="text-nowrap"><?= Fmt::date($e['filed_at']) ?></td>
                    <td class="text-end d-none d-md-table-cell text-muted"><?= Fmt::lag(Fmt::lagHours($e['filed_at'], $e['indexed_at'])) ?></td>
                    <td class="text-end"><a href="<?= Fmt::entityUrl($e) ?>" tabindex="-1" aria-hidden="true">View →</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
