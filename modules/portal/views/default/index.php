<?php

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var string|null $latestDay */
/** @var int $latestCount */
/** @var int $total */
/** @var array|null $adminStats */

use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Dashboard';
?>
<div class="row">
    <div class="col-sm-6 col-lg-3">
        <div class="small-box bg-info"><div class="inner"><h3><?= Fmt::num($latestCount) ?></h3><p>New filings<?= $latestDay ? ' · ' . Fmt::date($latestDay) : '' ?></p></div><div class="icon"><i class="fas fa-building"></i></div>
            <a href="<?= Url::to(['/new/index']) ?>" class="small-box-footer">Browse new filings <i class="fas fa-arrow-circle-right"></i></a></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="small-box bg-success"><div class="inner"><h3><?= Fmt::num($total) ?></h3><p>Companies indexed</p></div><div class="icon"><i class="fas fa-database"></i></div>
            <a href="<?= Url::to(['/search/index']) ?>" class="small-box-footer">Search all <i class="fas fa-arrow-circle-right"></i></a></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="small-box bg-warning"><div class="inner"><h3><?= Html::encode(ucfirst($user->plan)) ?></h3><p>Your plan</p></div><div class="icon"><i class="fas fa-tag"></i></div>
            <a href="<?= Url::to(['/pricing/index']) ?>" class="small-box-footer">See plans <i class="fas fa-arrow-circle-right"></i></a></div>
    </div>
    <?php if ($adminStats): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="small-box bg-danger"><div class="inner"><h3><?= Fmt::num($adminStats['optOutPending']) ?></h3><p>Opt-out requests pending · <?= Fmt::num($adminStats['waitlist']) ?> on waitlist</p></div><div class="icon"><i class="fas fa-user-shield"></i></div>
                <span class="small-box-footer">Admin</span></div>
        </div>
    <?php endif ?>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Welcome<?= $user->name ? ', ' . Html::encode($user->name) : '' ?></h3></div>
            <div class="card-body">
                <p>Your free account is active. Paid features are opening soon; join the waitlist from the <a href="<?= Url::to(['/pricing/index', '#' => 'waitlist']) ?>">pricing page</a> and we’ll email you first.</p>
                <ul class="mb-0">
                    <li>Full company records with officers and addresses (Pro)</li>
                    <li>Saved-search alerts delivered every morning (Pro)</li>
                    <li>CSV export and API access (Pro / Business)</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Account</h3></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= Html::encode($user->email) ?></dd>
                    <dt class="col-sm-4">Plan</dt><dd class="col-sm-8"><?= Html::encode(ucfirst($user->plan)) ?></dd>
                    <dt class="col-sm-4">Member since</dt><dd class="col-sm-8"><?= Fmt::date(substr($user->created_at, 0, 10)) ?></dd>
                    <dt class="col-sm-4">Last sign-in</dt><dd class="col-sm-8"><?= Fmt::dateTime($user->last_login_at) ?></dd>
                </dl>
            </div>
            <div class="card-footer"><a href="<?= Url::to(['/portal/account/index']) ?>" class="btn btn-sm btn-primary">Edit account</a></div>
        </div>
    </div>
</div>
