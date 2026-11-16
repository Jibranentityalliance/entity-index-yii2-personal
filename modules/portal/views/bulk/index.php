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
?>
<p class="text-muted">A full copy of every published company, rebuilt once a day: one national file and one file per state, as gzipped CSV (UTF-8) with the same columns as the CSV export.
    Check a download against the SHA-256 shown next to it.
    <?php if ($cap !== null): ?>Downloads today: <strong><?= Fmt::num($usedToday) ?> of <?= Fmt::num($cap) ?></strong>, resets at midnight UTC.<?php endif ?></p>

<?php if ($date === null): ?>
    <div class="card"><div class="p-4 text-center text-muted">No files have been built yet. The first build runs overnight.</div></div>
<?php else: ?>
    <div class="d-flex flex-wrap mb-2">
        <?php foreach ($dates as $d): ?>
            <a class="btn btn-sm mr-2 mb-2 <?= $d === $date ? 'btn-primary' : 'btn-default' ?>" href="<?= Url::to(['/portal/bulk/index', 'date' => $d]) ?>"><?= Html::encode(Fmt::date($d)) ?></a>
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
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
