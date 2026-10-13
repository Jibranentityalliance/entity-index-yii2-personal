<?php

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var string|null $latestDay */
/** @var array|null $latest */
/** @var array $stats */
/** @var int $total */
/** @var int $week */
/** @var array|null $adminStats */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Dashboard';
$latestCount = $latest['total'] ?? 0;
?>
<div class="row">
    <div class="col-sm-6 col-lg-3">
        <div class="small-box bg-info"><div class="inner"><h3><?= Fmt::num($latestCount) ?></h3><p>New filings<?= $latestDay ? ' · ' . Fmt::date($latestDay) : '' ?></p></div><div class="icon"><i class="fas fa-building"></i></div>
            <a href="<?= Url::to(['/portal/new/index']) ?>" class="small-box-footer">Browse new filings <i class="fas fa-arrow-circle-right"></i></a></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="small-box bg-success"><div class="inner"><h3><?= Fmt::num($week) ?></h3><p>Filings, last 7 days</p></div><div class="icon"><i class="fas fa-calendar-week"></i></div>
            <a href="<?= Url::to(['/portal/states/index']) ?>" class="small-box-footer">Coverage by state <i class="fas fa-arrow-circle-right"></i></a></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="small-box bg-warning"><div class="inner"><h3><?= Fmt::num($total) ?></h3><p>Companies indexed</p></div><div class="icon"><i class="fas fa-database"></i></div>
            <a href="<?= Url::to(['/portal/search/index']) ?>" class="small-box-footer">Search all <i class="fas fa-arrow-circle-right"></i></a></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="small-box bg-secondary"><div class="inner"><h3><?= Html::encode(ucfirst($user->plan)) ?></h3><p>Your plan<?= $user->isAdmin() ? ' · admin' : '' ?></p></div><div class="icon"><i class="fas fa-tag"></i></div>
            <a href="<?= Url::to(['/pricing/index']) ?>" class="small-box-footer">See plans <i class="fas fa-arrow-circle-right"></i></a></div>
    </div>
</div>

<?php if ($adminStats): ?>
    <div class="alert alert-light border">
        <strong>Admin:</strong>
        <?= Fmt::num($adminStats['optOutPending']) ?> opt-out requests pending ·
        <?= Fmt::num($adminStats['contactNew']) ?> new contact messages ·
        <?= Fmt::num($adminStats['waitlist']) ?> on waitlist
    </div>
<?php endif ?>

<div class="card card-primary card-outline">
    <div class="card-body">
        <form action="<?= Url::to(['/portal/search/index']) ?>" role="search">
            <div class="form-row">
                <div class="col-md-6 mb-2"><label for="dq" class="sr-only">Search</label><input id="dq" class="form-control form-control-lg" name="q" type="search" placeholder="Company name, officer or agent name, or filing number" autofocus></div>
                <div class="col-md-3 mb-2"><label for="dstate" class="sr-only">State</label>
                    <select id="dstate" class="form-control form-control-lg" name="state"><option value="">All states</option>
                        <?php foreach (States::all() as $s): ?><option value="<?= $s['code'] ?>"><?= Html::encode($s['name']) ?></option><?php endforeach ?>
                    </select></div>
                <div class="col-md-3 mb-2"><button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fas fa-search"></i> Search</button></div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Newest filings<?= $latestDay ? ' · ' . Fmt::dayLong($latestDay) : '' ?></h3>
        <div class="card-tools"><a href="<?= Url::to(['/portal/new/index']) ?>">See all <?= Fmt::num($latestCount) ?> →</a></div>
    </div>
    <div class="card-body p-0"><?= $this->render('/_partials/entity-table', ['items' => $latest['items'] ?? [], 'empty' => 'No filings indexed yet.']) ?></div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">New businesses by state (latest filing day)</h3><div class="card-tools"><a href="<?= Url::to(['/portal/states/index']) ?>">Coverage &amp; freshness →</a></div></div>
    <div class="card-body">
        <div class="ei-state-grid">
            <?php foreach (States::all() as $s): $n = $stats[$s['code']]['latestDayCount'] ?? 0; ?>
                <a href="<?= Fmt::stateUrl($s['code']) ?>" class="ei-state-tile"><span><?= Html::encode($s['name']) ?></span><span class="n<?= $n ? '' : ' zero' ?>"><?= $n ? Fmt::num($n) : '–' ?></span></a>
            <?php endforeach ?>
        </div>
    </div>
</div>
