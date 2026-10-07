<?php

/** @var yii\web\View $this */
/** @var string $basePath  route of the page, e.g. '/new/index' */
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
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="text-muted small">Filing day:</span>
    <div class="chips">
        <?php foreach ($days as $d): ?>
            <a href="<?= $href($d) ?>" class="chip<?= $d === $day ? ' on' : '' ?>"><?= Fmt::dayShort($d) ?></a>
        <?php endforeach ?>
    </div>
</div>
<div class="card-ei flush">
    <div class="card-head">
        <h2 class="fs-5 m-0"><?= Fmt::num($res['total']) ?> new <?= $res['total'] === 1 ? 'filing' : 'filings' ?> · <?= Fmt::dayLong($day) ?></h2>
        <a href="<?= Url::to(['/pricing/index', 'plan' => 'pro']) ?>" class="btn btn-outline-secondary btn-sm ms-auto">Export CSV</a>
    </div>
    <?= $this->render('//_partials/entity-table', ['items' => $res['items'], 'showState' => !$stateCode, 'empty' => 'No filings indexed for this day yet.']) ?>
    <?= $this->render('//_partials/pager', ['page' => $res['page'], 'pageSize' => $res['pageSize'], 'total' => $res['total'], 'hrefFor' => fn(int $p) => $href($day, $p)]) ?>
</div>
<?php if (!$stateCode): ?>
    <section class="section">
        <div class="section-head"><h2>Browse by state</h2></div>
        <div class="chips">
            <?php foreach (States::all() as $s): ?><a class="chip" href="<?= Fmt::stateUrl($s['code']) ?>"><?= Html::encode($s['name']) ?></a><?php endforeach ?>
        </div>
    </section>
<?php endif ?>
