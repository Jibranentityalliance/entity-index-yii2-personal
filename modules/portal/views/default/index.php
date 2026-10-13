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
    <?php foreach ([
        ['info', 'fa-building', 'Latest day', Fmt::num($latestCount), ['/portal/new/index']],
        ['success', 'fa-calendar-week', 'Last 7 days', Fmt::num($week), ['/portal/states/index']],
        ['warning', 'fa-database', 'Indexed', Fmt::num($total), ['/portal/search/index']],
        ['secondary', 'fa-tag', 'Your plan', ucfirst($user->plan), ['/pricing/index']],
    ] as [$color, $icon, $label, $value, $route]): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="info-box">
                <span class="info-box-icon bg-<?= $color ?>"><i class="fas <?= $icon ?>"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><a href="<?= Url::to($route) ?>" class="text-reset stretched-link"><?= Html::encode($label) ?></a></span>
                    <span class="info-box-number"><?= Html::encode($value) ?></span>
                </div>
            </div>
        </div>
    <?php endforeach ?>
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
        <div class="row">
            <?php foreach (States::all() as $s): $n = $stats[$s['code']]['latestDayCount'] ?? 0; ?>
                <div class="col-6 col-md-4 col-lg-3 mb-2">
                    <a href="<?= Fmt::stateUrl($s['code']) ?>" class="btn btn-default btn-block d-flex justify-content-between"><span><?= Html::encode($s['name']) ?></span><span class="<?= $n ? 'font-weight-bold' : 'text-muted' ?>"><?= $n ? Fmt::num($n) : '–' ?></span></a>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
