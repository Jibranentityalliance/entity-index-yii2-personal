<?php

/** @var yii\web\View $this */
/** @var string $basePath  route of the page, e.g. '/portal/new/index' */
/** @var array $routeParams extra route params, e.g. ['state' => 'fl'] */
/** @var string $day */
/** @var string[] $days */
/** @var array $res */
/** @var string|null $stateCode */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$stateCode = $stateCode ?? null;
$href = function (string $d, int $p = 1) use ($basePath, $routeParams, $days): string {
    $r = [$basePath] + $routeParams;
    if ($d !== $days[0]) {
        $r['day'] = $d;
    }
    if ($p > 1) {
        $r['page'] = $p;
    }
    return Url::to($r);
};
?>
<div class="mb-3">
    <span class="text-muted small mr-2">Filing day:</span>
    <?php foreach ($days as $d): ?>
        <a href="<?= $href($d) ?>" class="btn btn-sm mr-1 mb-1 <?= $d === $day ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= Fmt::dayShort($d) ?></a>
    <?php endforeach ?>
</div>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= Fmt::num($res['total']) ?> new <?= $res['total'] === 1 ? 'filing' : 'filings' ?> · <?= Fmt::dayLong($day) ?></h3>
        <div class="card-tools"><a href="<?= Url::to(['/pricing/index', 'plan' => 'pro']) ?>" class="btn btn-default btn-sm" title="CSV export is part of Pro"><i class="fas fa-download"></i> Export CSV <span class="badge badge-secondary">Pro</span></a></div>
    </div>
    <div class="card-body p-0">
        <?= $this->render('/_partials/entity-table', ['items' => $res['items'], 'showState' => !$stateCode, 'empty' => 'No filings indexed for this day yet.']) ?>
    </div>
    <?= $this->render('/_partials/pager', ['page' => $res['page'], 'pageSize' => $res['pageSize'], 'total' => $res['total'], 'hrefFor' => fn(int $p) => $href($day, $p)]) ?>
</div>
<?php if (!$stateCode): ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Browse by state</h3></div>
        <div class="card-body">
            <?php foreach (States::all() as $s): ?><a class="btn btn-sm btn-outline-secondary mr-1 mb-1" href="<?= Fmt::stateUrl($s['code']) ?>"><?= Html::encode($s['name']) ?></a><?php endforeach ?>
        </div>
    </div>
<?php endif ?>
