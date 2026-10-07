<?php

/** @var yii\web\View $this */
/** @var array $params */
/** @var array $res */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$q = $params['q'];
$this->title = $q ? "“{$q}” — company search" : 'Search new businesses';
$this->params['noindex'] = true; // result pages shouldn't compete with company pages
$st = $params['state'] ? States::byCode($params['state']) : null;

$hrefFor = function (int $p) use ($params): string {
    $qs = array_filter($params, fn($v, $k) => $v !== null && $v !== '' && !in_array($k, ['page', 'pageSize'], true), ARRAY_FILTER_USE_BOTH);
    if ($p > 1) {
        $qs['page'] = $p;
    }
    return Url::to(['/search/index'] + $qs);
};
?>
<div class="container">
    <div class="page-head">
        <h1 class="fs-2">
            <?= $q ? 'Results for “' . Html::encode($q) . '”' : 'Search new businesses' ?>
            <?php if ($st): ?><span class="text-muted"> in <?= $params['county'] ? Html::encode($params['county']) . ' County, ' : '' ?><?= Html::encode($st['name']) ?></span><?php endif ?>
        </h1>
        <p class="text-muted mb-0"><?= Fmt::num($res['total']) ?> <?= $res['total'] === 1 ? 'company' : 'companies' ?> · search by company name, officer or agent name, or state filing number.</p>
    </div>

    <form action="<?= Url::to(['/search/index']) ?>" class="card-ei mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="s-q" class="form-label">Keywords</label>
                <input id="s-q" class="form-control" name="q" type="search" value="<?= Html::encode($q) ?>" placeholder="e.g. roofing, Garcia, L26000123456">
            </div>
            <div class="col-md-3">
                <label for="s-state" class="form-label">State</label>
                <select id="s-state" class="form-select" name="state">
                    <option value="">All states</option>
                    <?php foreach (States::all() as $s): ?><option value="<?= $s['code'] ?>"<?= $params['state'] === $s['code'] ? ' selected' : '' ?>><?= Html::encode($s['name']) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="s-type" class="form-label">Entity type</label>
                <select id="s-type" class="form-select" name="type">
                    <option value="">Any type</option>
                    <?php foreach (States::ENTITY_TYPES as $t): ?><option<?= $params['type'] === $t ? ' selected' : '' ?>><?= Html::encode($t) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="s-from" class="form-label">Filed from</label>
                <input id="s-from" class="form-control" name="from" type="date" value="<?= Html::encode($params['from']) ?>">
            </div>
            <div class="col-md-3">
                <label for="s-to" class="form-label">Filed to</label>
                <input id="s-to" class="form-control" name="to" type="date" value="<?= Html::encode($params['to']) ?>">
            </div>
            <?php if ($params['county']): ?><input type="hidden" name="county" value="<?= Html::encode($params['county']) ?>"><?php endif ?>
            <div class="col-md-6 d-flex gap-2">
                <button class="btn btn-ei" type="submit">Search</button>
                <a href="<?= Url::to(['/search/index']) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card-ei flush">
        <div class="card-head">
            <h3><?= Fmt::num($res['total']) ?> results</h3>
            <span class="ms-auto text-muted small">Free plan: 10 full lookups/day · <a href="<?= Url::to(['/pricing/index']) ?>">CSV export with Pro</a></span>
        </div>
        <?= $this->render('//_partials/entity-table', ['items' => $res['items'], 'empty' => 'No companies match those filters. Try fewer keywords or a wider date range.']) ?>
        <?= $this->render('//_partials/pager', ['page' => $res['page'], 'pageSize' => $res['pageSize'], 'total' => $res['total'], 'hrefFor' => $hrefFor]) ?>
    </div>
</div>
