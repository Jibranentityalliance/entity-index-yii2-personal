<?php

/** @var yii\web\View $this */
/** @var array $params */
/** @var array $res */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$q = $params['q'];
$this->title = 'Search companies';
$st = $params['state'] ? States::byCode($params['state']) : null;

$hrefFor = function (int $p) use ($params): string {
    $qs = array_filter($params, fn($v, $k) => $v !== null && $v !== '' && !in_array($k, ['page', 'pageSize'], true), ARRAY_FILTER_USE_BOTH);
    if ($p > 1) {
        $qs['page'] = $p;
    }
    return Url::to(['/portal/search/index'] + $qs);
};
?>
<div class="card">
    <form action="<?= Url::to(['/portal/search/index']) ?>">
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="s-q">Keywords</label>
                    <input id="s-q" class="form-control" name="q" type="search" value="<?= Html::encode($q) ?>" placeholder="Company name, officer or agent name, or filing number">
                </div>
                <div class="form-group col-md-3">
                    <label for="s-state">State</label>
                    <select id="s-state" class="form-control" name="state">
                        <option value="">All states</option>
                        <?php foreach (States::all() as $s): ?><option value="<?= $s['code'] ?>"<?= $params['state'] === $s['code'] ? ' selected' : '' ?>><?= Html::encode($s['name']) ?></option><?php endforeach ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="s-type">Entity type</label>
                    <select id="s-type" class="form-control" name="type">
                        <option value="">Any type</option>
                        <?php foreach (States::ENTITY_TYPES as $t): ?><option<?= $params['type'] === $t ? ' selected' : '' ?>><?= Html::encode($t) ?></option><?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="s-from">Filed from</label>
                    <input id="s-from" class="form-control" name="from" type="date" value="<?= Html::encode($params['from']) ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="s-to">Filed to</label>
                    <input id="s-to" class="form-control" name="to" type="date" value="<?= Html::encode($params['to']) ?>">
                </div>
                <?php if ($params['county']): ?><input type="hidden" name="county" value="<?= Html::encode($params['county']) ?>"><?php endif ?>
            </div>
        </div>
        <div class="card-footer">
            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
            <a href="<?= Url::to(['/portal/search/index']) ?>" class="btn btn-default">Reset</a>
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
