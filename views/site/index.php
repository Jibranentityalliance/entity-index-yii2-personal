<?php

/** @var yii\web\View $this */
/** @var array|null $stats from site_stats (app\services\MirrorStats), null until `php yii stats/refresh` has run */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = null;
$this->params['canonical'] = '/';

$s = $stats ?? [];
$num = fn(string $k) => isset($s[$k]) ? Fmt::num($s[$k]) : '—';
$ago = function (?string $iso): string {
    if ($iso === null) {
        return '';
    }
    $min = max(0, intdiv(time() - strtotime($iso), 60));
    return match (true) {
        $min < 1 => 'just now',
        $min < 60 => "$min min ago",
        $min < 48 * 60 => intdiv($min, 60) . 'h ago',
        default => intdiv($min, 1440) . 'd ago',
    };
};
$lag = $s['median_lag_days'] ?? null;

// What can be found: entity kinds with how many were added in the last 30 days ("Foreign LLC" etc. count under Foreign).
$types = ['LLC' => 0, 'Corporation' => 0, 'Nonprofit' => 0, 'Partnership' => 0, 'Foreign' => 0];
foreach ($s['types_30d'] ?? [] as $kind => $n) {
    $key = str_starts_with($kind, 'Foreign') ? 'Foreign' : $kind;
    if (isset($types[$key])) {
        $types[$key] += $n;
    }
}
$hasTypes = array_sum($types) > 0;
$typeInfo = [
    'LLC' => ['Limited liability companies', 'M4 7h16M4 12h16M4 17h10'],
    'Corporation' => ['Corporations and S-Corps', 'M4 20V8l8-4 8 4v12M9 20v-6h6v6'],
    'Nonprofit' => ['Nonprofit corporations', 'M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z'],
    'Partnership' => ['LPs and LLPs', 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 20c0-3 2.5-5 5-5s5 2 5 5m-2-5c.8-.6 2-1 3-1 2.5 0 5 2 5 5'],
    'Foreign' => ['Registered from another state', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z'],
];
$steps = [
    ['We track new registrations', 'Every day we pick up the LLCs, corporations and nonprofits that were just registered with the state.', 'M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15z'],
    ['We build the company record', 'Each business gets one clean record: filing details, status, registered agent and the officers behind it.', 'M9 12l2 2 4-4M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z'],
    ['Your agents reach out first', 'Your team works today’s list and contacts new owners while they are still choosing who to work with.', 'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z'],
];
$icon = fn(string $d, int $size = 22) => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
$check = '<svg class="lp-check" width="18" height="18" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8.5l3 3 7-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>

<!-- 1. Hero banner -->
<section class="lp-hero">
    <div class="container text-center">
        <span class="lp-eyebrow"><span class="live-dot"></span>
            <?= isset($s['last_24h']) ? Fmt::num($s['last_24h']) . ' new companies in the last 24 hours' : 'Live data, updated every few minutes' ?></span>
        <h1 class="lp-title">Find new businesses<br class="d-none d-md-inline"> the day they register.</h1>
        <p class="lp-lede">EntityIndex tracks newly registered LLCs, corporations and nonprofits across the US and puts them in front of your agents every day.</p>

        <div class="lp-counter" aria-label="Companies in the index">
            <div class="lp-counter-num"><?= $num('total') ?></div>
            <div class="lp-counter-label">companies in the index<?= isset($s['computed_at']) ? ' · updated ' . Html::encode($ago($s['computed_at'])) : '' ?></div>
        </div>

        <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
            <a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-ei btn-lg px-4">Get access</a>
            <a href="#plans" class="btn btn-light btn-lg px-4 lp-btn-ghost">See plans</a>
        </div>

        <div class="lp-types">
            <?php foreach ($typeInfo as $key => [$sub, $path]): ?>
                <div class="lp-type">
                    <div class="lp-type-ico"><?= $icon($path) ?></div>
                    <div class="lp-type-name"><?= Html::encode($key === 'Foreign' ? 'Foreign entities' : ($key === 'LLC' ? 'LLCs' : $key . 's')) ?></div>
                    <div class="lp-type-sub"><?= Html::encode($sub) ?></div>
                    <?php if ($hasTypes): ?><div class="lp-type-n"><?= Fmt::num($types[$key]) ?> <span>in 30 days</span></div><?php endif ?>
                </div>
            <?php endforeach ?>
        </div>

        <?php if (!empty($s['feed'])): ?>
            <div class="lp-ticker" aria-label="Latest companies added">
                <span class="lp-ticker-label"><span class="live-dot"></span> Just added</span>
                <?php foreach (array_slice($s['feed'], 0, 5) as $f): ?>
                    <span class="lp-ticker-item">New <?= Html::encode($f['type']) ?> · <?= Html::encode(States::name($f['state'])) ?> <em><?= Html::encode($ago($f['at'])) ?></em></span>
                <?php endforeach ?>
            </div>
        <?php elseif ($stats === null): ?>
            <p class="text-muted small mt-4 mb-0">Live numbers appear here once the first data refresh has run.</p>
        <?php endif ?>
    </div>
</section>

<!-- 2. What EntityIndex is -->
<section class="lp-section">
    <div class="container">
        <div class="row align-items-center gy-5 gx-lg-5">
            <div class="col-lg-6">
                <p class="lp-kicker">What is EntityIndex</p>
                <h2 class="lp-h2">A fresh list of new businesses, ready for your agents every morning.</h2>
                <p class="lp-p">Thousands of companies register with a Secretary of State every day. Each one is a business that will soon need insurance, a bank, an accountant, a website and suppliers. EntityIndex collects those registrations into one place so your team can find them, qualify them and reach them first.</p>
                <ul class="lp-list">
                    <li><?= $check ?><div><strong>Daily new-business feed</strong><span>Every company added today, filterable by state and entity type.</span></div></li>
                    <li><?= $check ?><div><strong>Complete company records</strong><span>Filing number, status, filing date, registered agent and officers.</span></div></li>
                    <li><?= $check ?><div><strong>Members only</strong><span>Records are available to signed-in agents and teams, not the public.</span></div></li>
                </ul>
                <div class="lp-mini-stats">
                    <div><strong><?= $num('last_7d') ?></strong><span>added in the last 7 days</span></div>
                    <div><strong><?= $num('states_30d') ?></strong><span>states active this month</span></div>
                    <div><strong><?= $lag === null ? '—' : ($lag === 0 ? 'Same day' : $lag . ($lag === 1 ? ' day' : ' days')) ?></strong><span>median time from filing</span></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="lp-mock" aria-hidden="true">
                    <div class="lp-mock-bar"><span></span><span></span><span></span><em>entityindex.com / company</em></div>
                    <div class="lp-mock-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="lp-mock-title">Example Holdings LLC</div>
                                <div class="text-muted small">Domestic LLC · Florida · Filing #L26000000000</div>
                            </div>
                            <span class="badge-pill-ei ok"><span class="dot"></span>Active</span>
                        </div>
                        <dl class="lp-mock-facts">
                            <dt>Filed</dt><dd>Today</dd>
                            <dt>Added to EntityIndex</dt><dd>Same day</dd>
                            <dt>Registered agent</dt><dd><span class="lp-blur" style="width:150px"></span></dd>
                            <dt>Principal address</dt><dd><span class="lp-blur" style="width:190px"></span></dd>
                        </dl>
                        <div class="lp-mock-sub">Officers</div>
                        <div class="lp-mock-person"><span class="lp-avatar"></span><span class="lp-blur" style="width:120px"></span><span class="lp-blur short"></span></div>
                        <div class="lp-mock-person"><span class="lp-avatar"></span><span class="lp-blur" style="width:96px"></span><span class="lp-blur short"></span></div>
                        <div class="lp-mock-cta">Sign in to see the full record</div>
                    </div>
                </div>
                <p class="text-muted small text-center mt-2 mb-0">Illustration of a company record. Real records are shown after sign-in.</p>
            </div>
        </div>
    </div>
</section>

<!-- 3. How it works -->
<section class="lp-section lp-alt">
    <div class="container">
        <div class="text-center mb-5">
            <p class="lp-kicker">How it works</p>
            <h2 class="lp-h2 mx-auto" style="max-width:640px">From state registration to your agent’s call list.</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($steps as $i => [$t, $d, $path]): ?>
                <div class="col-md-4">
                    <div class="lp-step">
                        <div class="lp-step-top"><div class="lp-step-ico"><?= $icon($path, 24) ?></div><span class="lp-step-n">0<?= $i + 1 ?></span></div>
                        <h3><?= Html::encode($t) ?></h3>
                        <p><?= Html::encode($d) ?></p>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<!-- 4. Plans -->
<section class="lp-section" id="plans">
    <div class="container">
        <div class="text-center mb-5">
            <p class="lp-kicker">Plans</p>
            <h2 class="lp-h2">Pick the plan that fits your team.</h2>
            <p class="lp-p mx-auto mb-0" style="max-width:560px">Billing is not live yet. Join the waitlist on any paid plan and we will contact you first.</p>
        </div>
        <div class="row g-4 align-items-stretch">
            <?php foreach (Yii::$app->params['tiers'] as $t): ?>
                <div class="col-md-6 col-xl-3">
                    <div class="lp-plan h-100<?= !empty($t['featured']) ? ' featured' : '' ?>">
                        <?php if (!empty($t['featured'])): ?><span class="lp-plan-badge">Most popular</span><?php endif ?>
                        <h3><?= Html::encode($t['name']) ?></h3>
                        <p class="lp-plan-for"><?= Html::encode($t['for']) ?></p>
                        <div class="lp-plan-price"><?= Html::encode($t['price']) ?><small><?= Html::encode($t['per']) ?></small></div>
                        <ul>
                            <?php foreach ($t['features'] as $f): ?><li><?= $check ?><span><?= Html::encode($f) ?></span></li><?php endforeach ?>
                        </ul>
                        <a href="<?= Url::to($t['url']) ?>" class="btn <?= !empty($t['featured']) ? 'btn-ei' : 'btn-outline-secondary' ?> w-100 mt-auto"><?= Html::encode($t['cta']) ?></a>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<!-- 5. Sign-up banner -->
<section class="pb-5">
    <div class="container">
        <div class="lp-banner">
            <div>
                <h2>Start finding new businesses today.</h2>
                <p>Create your account in a minute and see today’s new companies.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-light btn-lg px-4 fw-semibold">Create your account</a>
                <a href="<?= Url::to(['/site/contact', 'topic' => 'sales']) ?>" class="btn btn-outline-light btn-lg px-4">Talk to sales</a>
            </div>
        </div>
    </div>
</section>
