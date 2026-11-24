<?php

/** @var yii\web\View $this */
/** @var string[] $dates */
/** @var string|null $date */
/** @var array[] $files */
/** @var int|null $cap */
/** @var int $usedToday */

use app\helpers\Fmt;
use app\services\bulk\BulkCatalog;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Bulk data';
$tabs = [
    BulkCatalog::FULL => 'Full copy',
    BulkCatalog::DAILY => 'New companies, daily',
    BulkCatalog::WEEKLY => 'New companies, weekly',
];
$intro = [
    BulkCatalog::FULL => 'Every published company, rebuilt once a day. The last 7 builds are kept.',
    BulkCatalog::DAILY => 'Only the companies that went public on each day (midnight to midnight UTC), for the last 7 days. Rebuilt every night, so details removed at a person\'s request drop out of older files too.',
    BulkCatalog::WEEKLY => 'Only the companies that went public in each week (Monday to Sunday, UTC), for the last 4 full weeks. Rebuilt every night like the daily files.',
];
$dateLabel = fn(string $d) => $kind === BulkCatalog::WEEKLY ? Fmt::dayShort($d) . ' – ' . Fmt::date(BulkCatalog::weekEnd($d)) : Fmt::date($d);
?>
<div class="d-flex flex-wrap mb-3">
    <div class="btn-group flex-wrap" role="group" aria-label="File type">
        <?php foreach ($tabs as $k => $label): ?>
            <a class="btn btn-sm <?= $k === $kind ? 'btn-primary' : 'btn-default' ?>" href="<?= Url::to(['/portal/bulk/index', 'kind' => $k]) ?>"><?= Html::encode($label) ?></a>
        <?php endforeach ?>
    </div>
</div>

<p class="text-muted"><?= Html::encode($intro[$kind]) ?> One national file and one file per state, as gzipped CSV (UTF-8) with the same columns as the CSV export.
    Check a download against the SHA-256 shown next to it.
    <?php if ($cap !== null): ?>Downloads today, across all files: <strong><?= Fmt::num($usedToday) ?> of <?= Fmt::num($cap) ?></strong>, resets at midnight UTC.<?php endif ?></p>

<?php if ($date === null): ?>
    <div class="card"><div class="p-4 text-center text-muted">No files have been built yet. The first build runs overnight.</div></div>
<?php else: ?>
    <div class="d-flex flex-wrap mb-2">
        <?php foreach ($dates as $d): ?>
            <a class="btn btn-sm mr-2 mb-2 <?= $d === $date ? 'btn-primary' : 'btn-default' ?>" href="<?= Url::to(['/portal/bulk/index', 'kind' => $kind, 'date' => $d]) ?>"><?= Html::encode($dateLabel($d)) ?></a>
        <?php endforeach ?>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Scope</th>
                    <th class="text-right">Companies</th>
                    <th class="text-right">Size</th>
                    <th class="d-none d-md-table-cell">SHA-256</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($files as $f): ?>
                    <tr>
                        <td class="font-weight-bold"><?= Html::encode(BulkCatalog::label($f['scope'])) ?></td>
                        <td class="text-right"><?= Fmt::num($f['row_count']) ?></td>
                        <td class="text-right text-nowrap"><?= Html::encode(Fmt::bytes((int) $f['bytes'])) ?></td>
                        <td class="d-none d-md-table-cell text-muted"><code title="<?= Html::encode($f['sha256']) ?>"><?= Html::encode(substr($f['sha256'], 0, 16)) ?></code></td>
                        <td class="text-right"><a class="btn btn-primary btn-sm" href="<?= Url::to(['/portal/bulk/download', 'id' => $f['id']]) ?>"><i class="fas fa-download"></i> Download</a></td>
                    </tr>
                <?php endforeach ?>
                <?php if (count($files) === 1 && (int) $files[0]['row_count'] === 0): ?>
                    <tr><td colspan="5" class="text-muted">No companies went public in this period. The file holds the header row only.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
