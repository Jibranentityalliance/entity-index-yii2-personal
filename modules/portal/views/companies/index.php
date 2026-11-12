<?php

/** @var yii\web\View $this */
/** @var array $params */
/** @var array $res */
/** @var array|null $export */
/** @var array|null $free */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$q = $params['q'];
$this->title = 'Companies';
$st = $params['state'] ? States::byCode($params['state']) : null;
$filtered = (bool) array_filter(array_diff_key($params, ['page' => 1, 'pageSize' => 1]));

$filterRows = array_filter([
    'Keywords' => $q,
    'State' => $st['name'] ?? null,
    'County' => $params['county'],
    'Entity type' => $params['type'],
    'Status' => $params['status'],
    'Filed from' => $params['from'],
    'Filed to' => $params['to'],
]);
$exportRows = $export === null ? 0 : min($res['total'], $export['allowance']);

$hrefFor = function (int $p) use ($params): string {
    $qs = array_filter($params, fn($v, $k) => $v !== null && $v !== '' && !in_array($k, ['page', 'pageSize'], true), ARRAY_FILTER_USE_BOTH);
    if ($p > 1) {
        $qs['page'] = $p;
    }
    return Url::to(['/portal/companies/index'] + $qs);
};
?>
<div class="card">
    <form action="<?= Url::to(['/portal/companies/index']) ?>" class="card-body ei-filters">
        <div class="form-row align-items-center">
            <div class="col-lg">
                <label for="s-q" class="sr-only">Keywords</label>
                <input id="s-q" class="form-control" name="q" type="search" value="<?= Html::encode($q) ?>" placeholder="<?= $free !== null ? 'Name, agent or filing number' : 'Name, officer, agent or filing number' ?>">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="s-state" class="sr-only">State</label>
                <select id="s-state" class="form-control" name="state">
                    <option value="">All states</option>
                    <?php foreach (States::all() as $s): ?><option value="<?= $s['code'] ?>"<?= $params['state'] === $s['code'] ? ' selected' : '' ?>><?= Html::encode($s['name']) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="s-type" class="sr-only">Entity type</label>
                <select id="s-type" class="form-control" name="type">
                    <option value="">Any type</option>
                    <?php foreach (States::ENTITY_TYPES as $t): ?><option<?= $params['type'] === $t ? ' selected' : '' ?>><?= Html::encode($t) ?></option><?php endforeach ?>
                </select>
            </div>
            <?= $this->render('/_partials/daterange', ['id' => 's-dates', 'from' => $params['from'], 'to' => $params['to'], 'class' => 'col-sm-6 col-lg-3']) ?>
            <?php if ($params['county']): ?><input type="hidden" name="county" value="<?= Html::encode($params['county']) ?>"><?php endif ?>
            <div class="col-sm-6 col-lg-auto d-flex">
                <button class="btn btn-primary flex-fill" type="submit"><i class="fas fa-search"></i> Search</button>
                <?php if ($filtered): ?><a href="<?= Url::to(['/portal/companies/index']) ?>" class="btn btn-default ml-2">Reset</a><?php endif ?>
            </div>
        </div>
    </form>
</div>

<?php if ($free !== null): ?>
<div class="card" id="free-usage">
    <div class="card-header">
        <h3 class="card-title">Free plan</h3>
        <div class="card-tools"><a href="<?= Url::to(['/pricing/index', 'plan' => 'pro']) ?>" class="btn btn-sm btn-primary">Upgrade to Pro</a></div>
    </div>
    <div class="card-body">
        <?php $ratio = $free['lookupsPerDay'] > 0 ? $free['used'] / $free['lookupsPerDay'] : 1; $pct = min(100, (int) round($ratio * 100)); $tone = $ratio >= 0.8 ? 'bg-danger' : ($ratio >= 0.5 ? 'bg-warning' : 'bg-success'); ?>
        <p class="mb-2"><strong><?= Fmt::num($free['used']) ?></strong> of <?= Fmt::num($free['lookupsPerDay']) ?> company lookups used today.</p>
        <div class="progress rounded-pill mb-3"><div class="progress-bar rounded-pill <?= $tone ?>" role="progressbar" style="width: <?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
        <p class="text-muted mb-0">
            Search covers every filing. Company details open for filings from the last <?= (int) $free['historyDays'] ?> days<?= $free['floor'] ? ' (' . Fmt::dayShort($free['floor']) . ' to ' . Fmt::dayShort($free['ceiling']) . ')' : '' ?>; older ones are Pro.
            Opening a company page is a lookup; reopening the same company today is free. Resets at midnight New York time.
        </p>
    </div>
</div>
<?php endif ?>

<?php if ($export !== null): ?>
<div class="card" id="export-usage">
    <div class="card-header"><h3 class="card-title">CSV export this month</h3></div>
    <div class="card-body">
        <?php if ($export['limit'] === null): ?>
            <p class="mb-0"><strong><?= Fmt::num($export['used']) ?></strong> rows exported so far. Your account has no monthly export cap.</p>
        <?php else: ?>
            <?php $ratio = $export['limit'] > 0 ? $export['used'] / $export['limit'] : 1; $pct = min(100, (int) round($ratio * 100)); $tone = $ratio >= 0.8 ? 'bg-danger' : ($ratio >= 0.5 ? 'bg-warning' : 'bg-success'); ?>
            <p class="mb-2"><strong><?= Fmt::num($export['used']) ?></strong> of <?= Fmt::num($export['limit']) ?> export rows used.</p>
            <div class="progress rounded-pill mb-3"><div class="progress-bar rounded-pill <?= $tone ?>" role="progressbar" style="width: <?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
            <p class="text-muted mb-0">Up to <?= Fmt::num($export['perFile']) ?> rows per file. Resets <?= gmdate('M j, Y', $export['resetsAt']) ?> (UTC).</p>
        <?php endif ?>
    </div>
</div>
<?php endif ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <?= Fmt::num($res['total']) ?> <?= $res['total'] === 1 ? 'company' : 'companies' ?>
            <?= $q ? ' for “' . Html::encode($q) . '”' : '' ?>
            <?php if ($st): ?> in <?= $params['county'] ? Html::encode($params['county']) . ' County, ' : '' ?><?= Html::encode($st['name']) ?><?php endif ?>
        </h3>
        <div class="card-tools">
            <?php if ($export === null): ?>
                <a href="<?= Url::to(['/pricing/index']) ?>" class="btn btn-sm btn-default"><i class="fas fa-lock"></i> Export CSV is a Pro feature</a>
            <?php elseif ($export['allowance'] < 1): ?>
                <button type="button" class="btn btn-sm btn-default" disabled title="Export rows used up until <?= gmdate('M j', $export['resetsAt']) ?> (UTC)"><i class="fas fa-file-csv"></i> Export CSV</button>
            <?php elseif ($res['total'] > 0): ?>
                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#export-modal"><i class="fas fa-file-csv"></i> Export CSV</button>
            <?php endif ?>
        </div>
    </div>
    <div class="card-body p-0">
        <?= $this->render('/_partials/entity-table', ['items' => $res['items'], 'showCounty' => $free === null, 'lockBefore' => $free['floor'] ?? null, 'empty' => 'No companies match those filters. Try fewer keywords or a wider date range.']) ?>
    </div>
    <?= $this->render('/_partials/pager', ['page' => $res['page'], 'pageSize' => $res['pageSize'], 'total' => $res['total'], 'hrefFor' => $hrefFor]) ?>
</div>

<?php if ($export !== null && $export['allowance'] >= 1 && $res['total'] > 0): ?>
<div class="modal fade" id="export-modal" tabindex="-1" role="dialog" aria-labelledby="export-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="<?= Url::to(['/portal/export/companies']) ?>" method="post" id="export-form">
                <input type="hidden" name="<?= Html::encode(Yii::$app->request->csrfParam) ?>" value="<?= Html::encode(Yii::$app->request->csrfToken) ?>">
                <?php foreach (array_filter(array_diff_key($params, ['page' => 1, 'pageSize' => 1])) as $k => $v): ?>
                    <input type="hidden" name="<?= Html::encode($k) ?>" value="<?= Html::encode($v) ?>">
                <?php endforeach ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="export-modal-title">Export to CSV</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="export-error" role="alert"></div>
                    <div class="mb-4">
                        <div class="ei-big-number"><?= Fmt::num($exportRows) ?></div>
                        <div class="text-muted"><?= $exportRows === 1 ? 'row' : 'rows' ?> will be exported<?= $exportRows < $res['total'] ? ' of ' . Fmt::num($res['total']) . ' matching' : '' ?>, newest filing first</div>
                    </div>
                    <div class="ei-summary mb-3">
                        <span class="ei-summary-label">Filters</span>
                        <?php if ($filterRows): ?>
                            <dl>
                                <?php foreach ($filterRows as $label => $value): ?>
                                    <dt><?= Html::encode($label) ?></dt><dd><?= Html::encode($value) ?></dd>
                                <?php endforeach ?>
                            </dl>
                        <?php else: ?>
                            <span>None, every company</span>
                        <?php endif ?>
                    </div>
                    <?php if ($exportRows < $res['total']): ?>
                        <div class="alert alert-warning mb-3" role="alert"><?= $export['limit'] !== null && $export['limit'] - $export['used'] < $export['perFile'] ? 'Your remaining monthly allowance' : 'The per-file limit of ' . Fmt::num($export['perFile']) . ' rows' ?> cuts this file short. Narrow the filters to export the rest.</div>
                    <?php endif ?>
                    <?php if ($export['limit'] !== null): ?>
                        <p class="text-muted mb-0">Your export allowance goes from <?= Fmt::num($export['limit'] - $export['used']) ?> to <?= Fmt::num($export['limit'] - $export['used'] - $exportRows) ?> rows this month.</p>
                    <?php endif ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="export-submit"><i class="fas fa-file-csv"></i> Export <?= Fmt::num($exportRows) ?> <?= $exportRows === 1 ? 'row' : 'rows' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $this->registerJs(<<<'JS'
$('#export-form').on('submit', function (e) {
    e.preventDefault();
    var form = this, $btn = $('#export-submit'), label = $btn.html(), $err = $('#export-error').addClass('d-none');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Preparing file');
    fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(function (r) {
            if (!r.ok) {
                return r.json().catch(function () { return {}; }).then(function (j) { throw new Error(j.error || 'The export failed. Please try again.'); });
            }
            var m = /filename="?([^";]+)"?/.exec(r.headers.get('Content-Disposition') || '');
            return r.blob().then(function (b) { return {blob: b, name: m ? m[1] : 'entityindex-companies.csv'}; });
        })
        .then(function (f) {
            var a = document.createElement('a');
            a.href = URL.createObjectURL(f.blob);
            a.download = f.name;
            document.body.appendChild(a);
            a.click();
            a.remove();
            $btn.html('<i class="fas fa-check"></i> Downloaded');
            setTimeout(function () { window.location.reload(); }, 700);
        })
        .catch(function (err) {
            $err.text(err.message).removeClass('d-none');
            $btn.prop('disabled', false).html(label);
        });
});
JS) ?>
<?php endif ?>
