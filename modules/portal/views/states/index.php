<?php

/** @var yii\web\View $this */
/** @var array $params */
/** @var array $stats */
/** @var array<string,int>|null $counts */
/** @var array $states rows of States::all() that pass the filters */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'States';
$live = count(array_intersect_key($stats, States::all()));
$within24 = count(array_filter($stats, fn($s) => ($s['medianLagHours'] ?? 99) <= 24));
$filtered = (bool) array_filter($params);
$select = function (string $name, string $all, array $options) use ($params): string {
    $html = '<option value="">' . Html::encode($all) . '</option>';
    foreach ($options as $value => $o) {
        $html .= '<option value="' . Html::encode($value) . '"' . ((string) $params[$name] === (string) $value ? ' selected' : '') . '>' . Html::encode($o[0]) . '</option>';
    }
    return '<label for="st-' . $name . '" class="sr-only">' . Html::encode($all) . '</label><select id="st-' . $name . '" class="form-control" name="' . $name . '">' . $html . '</select>';
};
$day = fn(?string $d) => $d ? Fmt::date($d) : null;
$countsLabel = match (true) {
    $params['from'] && $params['to'] => 'Filed ' . $day($params['from']) . ' – ' . $day($params['to']),
    (bool) $params['from'] => 'Filed since ' . $day($params['from']),
    default => 'Filed until ' . $day($params['to']),
};
?>
<p class="text-muted">Every state’s new filings come from official sources: open-data APIs and bulk files where they exist, the state website where they don’t.
    <span class="badge badge-success"><?= $live ?> of 51 jurisdictions live</span> <span class="badge badge-success"><?= $within24 ?> with median lag ≤ 24h</span></p>

<div class="card">
    <form action="<?= Url::to(['/portal/states/index']) ?>" class="card-body ei-filters">
        <div class="form-row align-items-center">
            <div class="col-sm-6 col-lg">
                <label for="st-q" class="sr-only">Jurisdiction</label>
                <input id="st-q" class="form-control" name="q" type="search" value="<?= Html::encode($params['q']) ?>" placeholder="Jurisdiction">
            </div>
            <div class="col-sm-6 col-lg"><?= $select('source', 'Any source', States::SOURCES) ?></div>
            <div class="col-sm-6 col-lg"><?= $select('lag', 'Any median lag', States::LAGS) ?></div>
            <?= $this->render('/_partials/daterange', ['id' => 'st-dates', 'from' => $params['from'], 'to' => $params['to'], 'class' => 'col-sm-6 col-lg-3']) ?>
            <div class="col-sm-6 col-lg"><?= $select('status', 'Any status', States::HEALTH) ?></div>
            <div class="col-sm-6 col-lg-auto d-flex">
                <button class="btn btn-primary flex-fill" type="submit"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($filtered): ?><a href="<?= Url::to(['/portal/states/index']) ?>" class="btn btn-default ml-2">Reset</a><?php endif ?>
            </div>
        </div>
    </form>
</div>

<?= $this->render('_table', ['stats' => $stats, 'states' => $states, 'counts' => $counts, 'countsLabel' => $countsLabel,
    'empty' => 'No jurisdictions match those filters.']) ?>
