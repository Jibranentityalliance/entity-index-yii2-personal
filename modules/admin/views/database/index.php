<?php

/** @var yii\web\View $this */
/** @var array $summary */
/** @var array[] $items */
/** @var string $show */
/** @var int $page */
/** @var int $pageSize */
/** @var int $total */
/** @var string $q */

use app\helpers\Fmt;
use app\services\AdminStats;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Database';
$s = $summary;
$now = time();
$stat = fn(string $label, $value, string $hint = '') => '<div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body">'
    . '<div class="text-muted">' . Html::encode($label) . '</div>'
    . '<div class="h3 mb-0">' . Html::encode($value) . '</div>'
    . ($hint !== '' ? '<small class="text-muted">' . Html::encode($hint) . '</small>' : '')
    . '</div></div></div>';
$when = fn(?string $v) => $v ? Fmt::dateTime($v) : 'Never';
$in = function (?string $v) use ($now): string {
    if ($v === null) {
        return '';
    }
    $left = strtotime($v . ' UTC') - $now;
    if ($left <= 60) {
        return 'any moment';
    }
    $d = intdiv($left, 86400);
    $h = intdiv($left % 86400, 3600);
    $m = intdiv($left % 3600, 60);
    return 'in ' . match (true) {
        $d > 0 => $d . 'd ' . $h . 'h',
        $h > 0 => $h . 'h ' . $m . 'm',
        default => $m . 'm',
    };
};
$tabs = [
    AdminStats::COOLDOWN => ['In cooldown', $s['cooldown']],
    AdminStats::HELD => ['Held, no publish time', $s['held']],
    AdminStats::PUBLISHED => ['Published', $s['published']],
];
$this->registerJs("$('#select-all').on('change', function () { $('.row-select').prop('checked', this.checked); });");
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <span class="text-muted">Totals as of <?= Html::encode($when($s['computed_at'])) ?>, refreshed every 5 minutes. The list below is always live.</span>
    <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['/admin/database/index', 'show' => $show, 'refresh' => 1]) ?>"><i class="fas fa-sync-alt"></i> Refresh now</a>
</div>

<div class="row">
    <?= $stat('Companies', Fmt::num($s['total'])) ?>
    <?= $stat('Published', Fmt::num($s['published']), 'Visible to users and the API') ?>
    <?= $stat('In cooldown', Fmt::num($s['cooldown']), 'Going live at their publish time') ?>
    <?= $stat('Held', Fmt::num($s['held']), 'No publish time yet') ?>
</div>
<div class="row">
    <?= $stat('Next release', $s['next_release_at'] ? $in($s['next_release_at']) : 'None', $s['next_release_at'] ? Fmt::dateTime($s['next_release_at']) : '') ?>
    <?= $stat('Going live within 1 hour', Fmt::num($s['live_within_1h'])) ?>
    <?= $stat('Going live within 24 hours', Fmt::num($s['live_within_24h'])) ?>
    <?= $stat('Last company converted', $when($s['last_converted_at']), 'Newest indexed: ' . $when($s['newest_indexed_at'])) ?>
</div>
<div class="row">
    <?= $stat('Backfill position', 'Source id ' . Fmt::num($s['cursor_id']), 'Updated ' . $when($s['cursor_at'])) ?>
    <?= $stat('Queued changes', Fmt::num($s['queued_changes']), $s['queued_since'] ? 'Oldest ' . $when($s['queued_since']) : 'Queue is empty') ?>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabs as $key => [$label, $count]): ?>
        <li class="nav-item"><a class="nav-link<?= $show === $key ? ' active' : '' ?>" href="<?= Url::to(['/admin/database/index', 'show' => $key, 'q' => $q]) ?>"><?= Html::encode($label) ?> <span class="badge badge-light"><?= Fmt::num($key === $show ? $total : $count) ?></span></a></li>
    <?php endforeach ?>
</ul>

<div class="card">
    <form action="<?= Url::to(['/admin/database/index']) ?>" class="card-body ei-filters">
        <input type="hidden" name="show" value="<?= Html::encode($show) ?>">
        <div class="form-row align-items-center">
            <div class="col-sm-8 col-lg-9">
                <label for="db-q" class="sr-only">Company name or filing number</label>
                <input id="db-q" class="form-control" name="q" type="search" value="<?= Html::encode($q) ?>" placeholder="Company name starts with, or filing number">
            </div>
            <div class="col-sm-4 col-lg-3 d-flex">
                <button class="btn btn-primary flex-fill" type="submit"><i class="fas fa-search"></i> Search</button>
                <?php if ($q !== ''): ?><a href="<?= Url::to(['/admin/database/index', 'show' => $show]) ?>" class="btn btn-default ml-2">Reset</a><?php endif ?>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (!$items): ?>
        <div class="p-4 text-center text-muted"><?= match ($show) {
            AdminStats::COOLDOWN => $q === '' ? 'Nothing is in cooldown right now.' : 'No companies in cooldown match.',
            AdminStats::PUBLISHED => 'No published companies match.',
            default => $q === '' ? 'No held companies.' : 'No held companies match.',
        } ?></div>
    <?php else: ?>
        <?= Html::beginForm(['/admin/database/publish'], 'post') ?>
        <input type="hidden" name="show" value="<?= Html::encode($show) ?>">
        <input type="hidden" name="page" value="<?= (int) $page ?>">
        <input type="hidden" name="q" value="<?= Html::encode($q) ?>">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <span class="text-muted">Tick companies, then release them now or hold them back. A manual choice stays when the source row is synced again.</span>
            <span>
                <?php if ($show !== AdminStats::PUBLISHED): ?><button type="submit" name="mode" value="release" class="btn btn-primary btn-sm"><i class="fas fa-bolt"></i> Release now</button><?php endif ?>
                <?php if ($show !== AdminStats::HELD): ?><button type="submit" name="mode" value="hold" class="btn btn-default btn-sm ml-1"><i class="fas fa-pause"></i> Hold</button><?php endif ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th class="w-auto"><input type="checkbox" id="select-all" aria-label="Select all on this page"></th>
                    <th>Company</th>
                    <th>State</th>
                    <th class="d-none d-md-table-cell">Type</th>
                    <th class="d-none d-md-table-cell">Filing number</th>
                    <th>Imported</th>
                    <?php if ($show === AdminStats::COOLDOWN): ?><th>Goes live</th><th>In</th><?php endif ?>
                    <?php if ($show === AdminStats::PUBLISHED): ?><th>Published</th><?php endif ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $r): ?>
                    <tr>
                        <td><input type="checkbox" class="row-select" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="Select <?= Html::encode($r['name']) ?>"></td>
                        <td class="font-weight-bold"><?= Html::encode(Fmt::na($r['name'])) ?>
                            <?php if ($r['publish_override']): ?><span class="badge badge-warning ml-1" title="Set by an admin"><?= $r['publish_override'] === 'hold' ? 'Manual hold' : 'Manual release' ?></span><?php endif ?></td>
                        <td><?= Html::encode($r['state'] === 'NA' ? 'N/A' : $r['state']) ?></td>
                        <td class="d-none d-md-table-cell text-muted"><?= Html::encode(Fmt::na($r['entity_type'])) ?></td>
                        <td class="d-none d-md-table-cell text-muted"><?= Html::encode(Fmt::na($r['state_filing_number'])) ?></td>
                        <td class="text-nowrap"><?= Html::encode($when($r['indexed_at'])) ?></td>
                        <?php if ($show === AdminStats::COOLDOWN): ?>
                            <td class="text-nowrap"><?= Html::encode($when($r['publish_at'])) ?></td>
                            <td class="text-nowrap"><span class="badge badge-info"><?= Html::encode($in($r['publish_at'])) ?></span></td>
                        <?php endif ?>
                        <?php if ($show === AdminStats::PUBLISHED): ?><td class="text-nowrap"><?= Html::encode($when($r['publish_at'])) ?></td><?php endif ?>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= Html::endForm() ?>
        <?= $this->render('@app/modules/portal/views/_partials/pager', [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'hrefFor' => fn(int $p) => Url::to(['/admin/database/index', 'show' => $show, 'page' => $p, 'q' => $q]),
        ]) ?>
    <?php endif ?>
</div>
