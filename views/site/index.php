<?php

/** @var yii\web\View $this */
/** @var array|null $stats from site_stats (app\services\MirrorStats), null until `php yii stats/refresh` has run */

use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = null;
$this->params['canonical'] = '/';
$faqAll = require Yii::getAlias('@app/config/faq.php');
$faq = array_values(array_filter($faqAll, fn($f) => in_array($f[1], [
    'Do I need an account to search?', 'Do you include phone numbers and emails?', 'When do paid plans open?', 'Is this data legal to use for marketing?',
], true)));

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
$daily = $s['daily'] ?? [];
$maxDay = max(array_merge([1], array_column($daily, 'count')));
$top = $s['top_states_7d'] ?? [];
$maxTop = max(array_merge([1], array_column($top, 'count')));
$lag = $s['median_lag_days'] ?? null;

$record = [
    ['Company', 'Name, state, filing number, entity type, status and filing date'],
    ['Registered agent', 'Who receives legal notices for the business, with address'],
    ['Officers & owners', 'Names, titles and addresses on paid plans'],
    ['Daily new-filings feed', 'Every company added today, state by state'],
    ['Search', 'By company, officer, agent or filing number, with state and date filters'],
    ['Alerts, exports & API', 'Saved-search alerts, CSV export and API access are coming with paid plans'],
];
$steps = [
    ['Find', 'Open the daily feed or search by state, entity type and filing date to see who just registered.'],
    ['Qualify', 'Check the company record, registered agent and officers to decide who is worth a call.'],
    ['Reach out first', 'Contact new owners while they are still choosing an insurer, accountant, bank or vendor.'],
];
?>
<section class="hero hero-v2">
    <div class="container">
        <div class="row align-items-center gy-4 gx-lg-5">
            <div class="col-lg-7">
                <p class="mb-3">
                    <span class="badge-pill-ei ok"><span class="live-dot"></span>
                        <?= isset($s['last_24h']) ? Fmt::num($s['last_24h']) . ' companies added in the last 24 hours' : 'Live data' ?></span>
                </p>
                <h1>Reach new businesses the day they register.</h1>
                <p class="lede">EntityIndex gives your agents a fresh list of newly registered LLCs and corporations every day, with the filing details,
                    registered agent and officers they need to make the first call.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-ei btn-lg">Get access</a>
                    <a href="<?= Url::to(['/portal/auth/login']) ?>" class="btn btn-outline-secondary btn-lg">Sign in</a>
                </div>
                <p class="text-muted small mt-3 mb-0">For agents and sales teams. Company records are available to signed-in members only.
                    <a href="<?= Url::to(['/pricing/index']) ?>">See plans</a></p>
            </div>
            <div class="col-lg-5">
                <div class="card-ei feed" aria-label="Latest companies added">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong>Latest additions</strong>
                        <span class="badge-pill-ei ok"><span class="dot"></span>Live</span>
                    </div>
                    <?php if (!empty($s['feed'])): ?>
                        <?php foreach ($s['feed'] as $f): ?>
                            <div class="feed-row">
                                <span><span class="feed-type">New <?= Html::encode($f['type']) ?></span> <span class="text-muted">· <?= Html::encode(\app\helpers\States::name($f['state'])) ?></span></span>
                                <span class="text-muted feed-time"><?= Html::encode($ago($f['at'])) ?></span>
                            </div>
                        <?php endforeach ?>
                        <p class="text-muted small mb-0 mt-2">Names, agents and officers are shown after sign-in.</p>
                    <?php else: ?>
                        <p class="text-muted small mb-0 mt-2">The live feed appears here once the first data refresh has run.</p>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <div class="row g-3 stats" aria-label="Key numbers">
        <div class="col-6 col-lg"><div class="stat"><div class="v"><?= $num('total') ?></div><div class="l">Companies in the database</div></div></div>
        <div class="col-6 col-lg"><div class="stat"><div class="v"><?= $num('last_24h') ?></div><div class="l">Added in the last 24 hours</div></div></div>
        <div class="col-6 col-lg"><div class="stat"><div class="v"><?= $num('last_7d') ?></div><div class="l">Added in the last 7 days</div></div></div>
        <div class="col-6 col-lg"><div class="stat"><div class="v"><?= $num('states_30d') ?></div><div class="l">States with new companies, last 30 days</div></div></div>
        <div class="col-12 col-lg"><div class="stat"><div class="v"><?= $lag === null ? '—' : ($lag === 0 ? 'Same day' : $lag . ($lag === 1 ? ' day' : ' days')) ?></div><div class="l">Median time from state filing to our list</div></div></div>
    </div>
    <?php if (isset($s['computed_at'])): ?>
        <p class="text-muted small mt-2 mb-0">Live numbers, updated <?= Html::encode($ago($s['computed_at'])) ?>.</p>
    <?php endif ?>

    <?php if ($daily): ?>
        <section class="section">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card-ei h-100">
                        <h2 class="h6 mb-1">New companies added per day</h2>
                        <p class="text-muted small mb-3">Last <?= count($daily) ?> days</p>
                        <div class="bars" role="img" aria-label="Bar chart of new companies added per day, last <?= count($daily) ?> days. Values are in the table below.">
                            <?php foreach ($daily as $i => $d): ?>
                                <?php $label = Fmt::dayShort($d['day']) . ': ' . Fmt::num($d['count']) . ' companies'; ?>
                                <div class="bar-col" tabindex="0" data-tip="<?= Html::encode($label) ?>">
                                    <div class="bar<?= $i === count($daily) - 1 ? ' today' : '' ?>" style="height: <?= round($d['count'] / $maxDay * 100, 1) ?>%"></div>
                                </div>
                            <?php endforeach ?>
                        </div>
                        <div class="bars-axis text-muted small"><span><?= Html::encode(Fmt::dayShort($daily[0]['day'])) ?></span><span>Today (so far)</span></div>
                        <details class="mt-2 small">
                            <summary class="text-muted">Show as table</summary>
                            <table class="table table-sm mt-2 mb-0"><thead><tr><th>Day</th><th class="text-end">Companies added</th></tr></thead><tbody>
                                <?php foreach (array_reverse($daily) as $d): ?><tr><td><?= Html::encode(Fmt::dayShort($d['day'])) ?></td><td class="text-end"><?= Fmt::num($d['count']) ?></td></tr><?php endforeach ?>
                            </tbody></table>
                        </details>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card-ei h-100">
                        <h2 class="h6 mb-1">Busiest states this week</h2>
                        <p class="text-muted small mb-3">New companies, last 7 days</p>
                        <?php if ($top): ?>
                            <?php foreach ($top as $t): ?>
                                <div class="hbar">
                                    <div class="hbar-head"><span><?= Html::encode($t['name']) ?></span><span class="fw-semibold"><?= Fmt::num($t['count']) ?></span></div>
                                    <div class="hbar-track"><div class="hbar-fill" style="width: <?= round($t['count'] / $maxTop * 100, 1) ?>%"></div></div>
                                </div>
                            <?php endforeach ?>
                        <?php else: ?>
                            <p class="text-muted small mb-0">No new companies in the last 7 days.</p>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif ?>

    <section class="section">
        <div class="section-head"><h2>How agents use EntityIndex</h2></div>
        <div class="row g-4 steps">
            <?php foreach ($steps as $i => [$t, $d]): ?>
                <div class="col-md-4">
                    <div class="step"><?= $i + 1 ?></div>
                    <h3 class="h6"><?= Html::encode($t) ?></h3>
                    <p class="text-muted mb-0"><?= Html::encode($d) ?></p>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><h2>What your agents get</h2><span class="ms-auto"></span><a href="<?= Url::to(['/site/features']) ?>">All features →</a></div>
        <div class="card-ei">
            <div class="row g-0 checklist">
                <?php foreach ($record as [$t, $d]): ?>
                    <div class="col-md-6 check-item">
                        <svg class="check-ico" viewBox="0 0 16 16" width="18" height="18" aria-hidden="true"><path d="M3 8.5l3 3 7-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <div><strong><?= Html::encode($t) ?></strong><div class="text-muted small"><?= Html::encode($d) ?></div></div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><h2>Plans</h2><span class="ms-auto"></span><a href="<?= Url::to(['/pricing/index']) ?>">Compare plans →</a></div>
        <div class="row g-3">
            <?php foreach (Yii::$app->params['tiers'] as $t): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card-ei tier h-100<?= !empty($t['featured']) ? ' featured' : '' ?>">
                        <h3 class="h6 m-0"><?= Html::encode($t['name']) ?></h3>
                        <div class="price mt-2"><?= Html::encode($t['price']) ?><small><?= Html::encode($t['per']) ?></small></div>
                        <div class="text-muted small"><?= Html::encode($t['for']) ?></div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><h2>Questions</h2><span class="ms-auto"></span><a href="<?= Url::to(['/site/faq']) ?>">All FAQs →</a></div>
        <div class="faq">
            <?php foreach ($faq as [$cat, $q, $a]): ?>
                <details><summary><?= Html::encode($q) ?></summary><p class="text-muted"><?= Html::encode($a) ?></p></details>
            <?php endforeach ?>
        </div>
    </section>

    <section class="section">
        <div class="card-ei cta-band d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div style="max-width:620px">
                <h2 class="h4 mb-1">Give your agents today’s new businesses</h2>
                <p class="text-muted mb-0">Create an account to browse the daily feed and company records. Upgrade when your team needs officers, alerts or exports.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-ei">Get access</a>
                <a href="<?= Url::to(['/site/contact', 'topic' => 'sales']) ?>" class="btn btn-outline-secondary">Talk to sales</a>
            </div>
        </div>
    </section>
</div>
