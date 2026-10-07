<?php

/** @var yii\web\View $this */
/** @var string|null $latestDay */
/** @var array|null $latest */
/** @var array $stats */
/** @var int $total */
/** @var float|null $medianLag */
/** @var int $week */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = null;
$this->params['canonical'] = '/';
$latestCount = $latest['total'] ?? 0;
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
        <p><span class="badge-pill-ei ok"><span class="live-dot"></span> <?= Fmt::num($latestCount) ?> new filings indexed<?= $latestDay ? ' · ' . Fmt::dayLong($latestDay) : '' ?></span></p>
        <h1>Every new US business, searchable within 24 hours.</h1>
        <p class="lede">New LLCs and corporations from all 50 states and DC, pulled from official Secretary of State records every day. Search free, get saved-search alerts, or plug in the API.</p>
        <form action="<?= Url::to(['/search/index']) ?>" class="search-hero" role="search">
            <label for="q" class="visually-hidden">Company name, officer or filing number</label>
            <input id="q" class="form-control form-control-lg" name="q" type="search" placeholder="Company name, officer, or filing number" autocomplete="off">
            <label for="state" class="visually-hidden">State</label>
            <select id="state" class="form-select form-select-lg" name="state">
                <option value="">All states</option>
                <?php foreach (States::all() as $s): ?><option value="<?= $s['code'] ?>"><?= Html::encode($s['name']) ?></option><?php endforeach ?>
            </select>
            <button class="btn btn-ei btn-lg" type="submit">Search</button>
        </form>
        <div class="hero-meta">
            <span>Try:</span>
            <a href="<?= Url::to(['/search/index', 'q' => 'roofing', 'state' => 'FL']) ?>">roofing in Florida</a>
            <a href="<?= Url::to(['/search/index', 'q' => 'holdings', 'state' => 'WY']) ?>">holdings in Wyoming</a>
            <a href="<?= Url::to(['/search/index', 'q' => 'consulting', 'type' => 'Corporation']) ?>">consulting corporations</a>
            <?php if (!empty($latest['items'][0])): ?><a href="<?= Fmt::entityUrl($latest['items'][0]) ?>">Open an example company page →</a><?php endif ?>
        </div>
    </div>
</section>

<div class="container">
    <div class="row g-3 stats">
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= Fmt::num($latestCount) ?></div><div class="l">New filings, latest day</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= Fmt::num($week) ?></div><div class="l">New filings, last 7 days</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= $medianLag !== null ? Fmt::lag($medianLag) : '—' ?></div><div class="l">Median time from filed → searchable</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="v"><?= count($stats) ?><span class="text-muted fs-6"> / 51</span></div><div class="l">Jurisdictions live · <?= Fmt::num($total) ?> records</div></div></div>
    </div>

    <section class="section">
        <div class="section-head">
            <h2>Newest filings</h2>
            <?php if ($latestDay): ?><span class="text-muted small"><?= Fmt::dayLong($latestDay) ?></span><?php endif ?>
            <span class="ms-auto"></span>
            <a href="<?= Url::to(['/new/index']) ?>">See all <?= Fmt::num($latestCount) ?> →</a>
        </div>
        <div class="card-ei flush">
            <?= $this->render('//_partials/entity-table', ['items' => $latest['items'] ?? [], 'empty' => 'No filings indexed yet today.']) ?>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <h2>New businesses by state</h2>
            <span class="text-muted small">Latest filing day</span>
            <span class="ms-auto"></span>
            <a href="<?= Url::to(['/states/index']) ?>">Coverage &amp; freshness →</a>
        </div>
        <div class="state-grid">
            <?php foreach (States::all() as $s): $n = $stats[$s['code']]['latestDayCount'] ?? 0; ?>
                <a href="<?= Fmt::stateUrl($s['code']) ?>" class="state-tile">
                    <span><?= Html::encode($s['name']) ?></span>
                    <span class="n<?= $n ? '' : ' zero' ?>"><?= $n ? Fmt::num($n) : '–' ?></span>
                </a>
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
        <div class="card-ei d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div style="max-width:620px">
                <h2 class="h4 mb-1">Get new filings in your inbox every morning</h2>
                <p class="text-muted mb-0">Save a search like “new LLCs in Broward County with contractor keywords” and get a daily email or webhook. Pro starts at $49/month.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= Url::to(['/pricing/index']) ?>" class="btn btn-ei">See plans</a>
                <a href="<?= Url::to(['/pricing/index', '#' => 'api']) ?>" class="btn btn-outline-secondary">API docs</a>
            </div>
        </div>
    </section>
</div>
