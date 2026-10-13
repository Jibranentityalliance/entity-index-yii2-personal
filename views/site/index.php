<?php

/** @var yii\web\View $this */
/** @var string|null $latestDay */
/** @var int $latestCount */
/** @var int $jurisdictions */
/** @var int $total */
/** @var float|null $medianLag */
/** @var int $week */

use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = null;
$this->params['canonical'] = '/';
$faq = array_slice(require Yii::getAlias('@app/config/faq.php'), 0, 4);
$features = [
    ['bi-search', 'Search every new business', 'Find companies by name, officer, registered agent or state filing number, then filter by state, entity type and filing date.'],
    ['bi-clock', 'Daily new-filings feed', 'See every LLC and corporation filed today (or any recent day), state by state, within 24 hours of the state record.'],
    ['bi-geo', 'Coverage you can verify', 'Per-state freshness dashboard: filings per day and the median time from state filing to searchable.'],
    ['bi-bell', 'Alerts and exports', 'Saved-search email alerts and CSV export are coming with Pro.'],
    ['bi-braces', 'API and bulk data', 'A JSON API with webhooks and daily bulk files for data teams are coming with Business and Data license plans.'],
    ['bi-link', 'Source-linked records', 'Every record keeps its state filing number, the pull time and a link back to the official source.'],
];
$buyers = [
    ['Insurance agents', 'Reach new businesses before they buy general liability, workers’ comp or commercial auto.'],
    ['Merchant services & lenders', 'Offer payment processing and working capital the week a company forms.'],
    ['Accountants & bookkeepers', 'Be the first CPA a new LLC hears from before its first tax deadline.'],
    ['Web & marketing agencies', 'New businesses need a site, a logo and a Google listing. Filter by industry keyword.'],
    ['Registered agent & compliance', 'Track formations, agent changes and annual-report status across states.'],
    ['Data & AI platforms', 'License a daily 51-jurisdiction feed with filing numbers and source links.'],
];
?>
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <p><span class="badge-pill-ei ok"><span class="live-dot"></span> <?= Fmt::num($latestCount) ?> new filings indexed<?= $latestDay ? ' · ' . Fmt::dayLong($latestDay) : '' ?></span></p>
                <h1>Every new US business, searchable within 24 hours.</h1>
                <p class="lede">New LLCs and corporations from all 50 states and DC, pulled from official Secretary of State records every day. Search, track and export the businesses that need what you sell.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-ei btn-lg">Create free account</a>
                    <a href="<?= Url::to(['/pricing/index']) ?>" class="btn btn-outline-secondary btn-lg">See pricing</a>
                </div>
                <p class="text-muted small mt-3 mb-0">Free plan. No credit card.</p>
            </div>
            <div class="col-lg-5">
                <div class="card-ei preview" aria-hidden="true">
                    <div class="d-flex justify-content-between align-items-center mb-2"><strong>New filings today</strong><span class="badge-pill-ei ok"><span class="dot"></span>Live</span></div>
                    <?php foreach ([['Coastal Roofing LLC', 'FL', '3h'], ['Northgate Holdings Inc.', 'WY', '5h'], ['Bright Path Consulting LLC', 'TX', '6h'], ['Maple & Pine Bakery LLC', 'OR', '8h']] as [$n, $s, $l]): ?>
                        <div class="preview-row"><span><?= Html::encode($n) ?></span><span class="text-muted"><?= $s ?> · <?= $l ?></span></div>
                    <?php endforeach ?>
                    <p class="text-muted small mb-0 mt-2">Illustration. Sign in to browse real filings.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <div class="row g-3 stats">
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= Fmt::num($latestCount) ?></div><div class="l">New filings, latest day</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= Fmt::num($week) ?></div><div class="l">New filings, last 7 days</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= $medianLag !== null ? Fmt::lag($medianLag) : '—' ?></div><div class="l">Median time from filed → searchable</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= $jurisdictions ?><span class="text-muted fs-6"> / 51</span></div><div class="l">Jurisdictions live · <?= Fmt::num($total) ?> records</div></div></div>
    </div>

    <section class="section">
        <div class="section-head"><h2>How it works</h2></div>
        <div class="row g-3">
            <?php foreach ([['1', 'We collect', 'Every day we pull new filings from each state’s official registry: open-data APIs, bulk files or the state website.'], ['2', 'We index', 'Records are cleaned, matched to registered agents and officers, and searchable within 24 hours of the state timestamp.'], ['3', 'You act', 'Search, browse the daily feed, and (soon) get alerts, exports or API access, then reach new businesses first.']] as [$n, $t, $d]): ?>
                <div class="col-md-4"><div class="buyer"><div class="step"><?= $n ?></div><h3><?= $t ?></h3><p><?= $d ?></p></div></div>
            <?php endforeach ?>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><h2>Everything you need to find new businesses</h2><span class="ms-auto"></span><a href="<?= Url::to(['/site/features']) ?>">All features →</a></div>
        <div class="row g-3">
            <?php foreach ($features as [$icon, $t, $d]): ?>
                <div class="col-md-6 col-lg-4"><div class="buyer"><h3><?= Html::encode($t) ?></h3><p><?= Html::encode($d) ?></p></div></div>
            <?php endforeach ?>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><h2>Built for teams that sell to new businesses</h2></div>
        <div class="row g-3">
            <?php foreach ($buyers as [$t, $d]): ?>
                <div class="col-md-6 col-lg-4"><div class="buyer"><h3><?= Html::encode($t) ?></h3><p><?= Html::encode($d) ?></p></div></div>
            <?php endforeach ?>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><h2>Simple pricing</h2><span class="ms-auto"></span><a href="<?= Url::to(['/pricing/index']) ?>">Compare plans →</a></div>
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
        <div class="card-ei d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div style="max-width:620px">
                <h2 class="h4 mb-1">Start with a free account</h2>
                <p class="text-muted mb-0">Search every new filing, browse the daily feed and check state coverage. Upgrade when you need full records, alerts or the API.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-ei">Create free account</a>
                <a href="<?= Url::to(['/site/contact']) ?>" class="btn btn-outline-secondary">Talk to us</a>
            </div>
        </div>
    </section>
</div>
