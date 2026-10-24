<?php

/** @var yii\web\View $this */
/** @var array $params */
/** @var array $res */

use app\helpers\Fmt;
use app\helpers\States;
use app\modules\portal\assets\DateRangeAsset;
use yii\helpers\Html;
use yii\helpers\Url;

DateRangeAsset::register($this);
$q = $params['q'];
$this->title = 'Companies';
$st = $params['state'] ? States::byCode($params['state']) : null;
$filtered = (bool) array_filter(array_diff_key($params, ['page' => 1, 'pageSize' => 1]));
$range = implode(' to ', array_filter([$params['from'], $params['to']]));

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
                <input id="s-q" class="form-control" name="q" type="search" value="<?= Html::encode($q) ?>" placeholder="Name, officer, agent or filing number">
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
            <div class="col-sm-6 col-lg-3 ei-daterange">
                <label for="s-dates" class="sr-only">Filing date</label>
                <i class="far fa-calendar ei-daterange-icon" aria-hidden="true"></i>
                <input id="s-dates" class="form-control" type="text" value="<?= Html::encode($range) ?>" placeholder="Filing date" autocomplete="off" data-ei-daterange>
                <input type="hidden" name="from" value="<?= Html::encode($params['from']) ?>">
                <input type="hidden" name="to" value="<?= Html::encode($params['to']) ?>">
            </div>
            <?php if ($params['county']): ?><input type="hidden" name="county" value="<?= Html::encode($params['county']) ?>"><?php endif ?>
            <div class="col-sm-6 col-lg-auto d-flex">
                <button class="btn btn-primary flex-fill" type="submit"><i class="fas fa-search"></i> Search</button>
                <?php if ($filtered): ?><a href="<?= Url::to(['/portal/companies/index']) ?>" class="btn btn-default ml-2">Reset</a><?php endif ?>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <?= Fmt::num($res['total']) ?> <?= $res['total'] === 1 ? 'company' : 'companies' ?>
            <?= $q ? ' for “' . Html::encode($q) . '”' : '' ?>
            <?php if ($st): ?> in <?= $params['county'] ? Html::encode($params['county']) . ' County, ' : '' ?><?= Html::encode($st['name']) ?><?php endif ?>
        </h3>
    </div>
    <div class="card-body p-0">
        <?= $this->render('/_partials/entity-table', ['items' => $res['items'], 'empty' => 'No companies match those filters. Try fewer keywords or a wider date range.']) ?>
    </div>
    <?= $this->render('/_partials/pager', ['page' => $res['page'], 'pageSize' => $res['pageSize'], 'total' => $res['total'], 'hrefFor' => $hrefFor]) ?>
</div>
