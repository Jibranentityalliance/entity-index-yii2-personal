<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Features';
$this->params['description'] = 'Search new US business filings, browse the daily feed, verify state coverage, and (soon) alerts, CSV export, API and bulk data.';
$this->params['canonical'] = '/features';
$rows = [
    ['Available now', 'Search', 'Find companies by name, officer or registered-agent name, or state filing number. Filter by state, entity type, county and filing date. Results are ranked: exact filing number first, then names that start with your query.'],
    ['Available now', 'Daily new-filings feed', 'Browse every filing by day and by state, newest first, with the time between the state record and our index shown on every row.'],
    ['Available now', 'Company records', 'Legal name, type, status, filing number, formation date, location, estimated industry (NAICS), registered agent and filing history, each with a link back to the official source.'],
    ['Available now', 'Coverage & freshness dashboard', 'For all 51 jurisdictions: filings per day, last update, and the median hours from state filing to searchable, so you know what you are buying.'],
    ['Pro', 'Full officer and address records', 'Officer, manager and member names with mailing and principal addresses.'],
    ['Pro', 'Saved-search alerts', 'Save a search such as “new LLCs in Broward County with contractor keywords” and get an email every morning.'],
    ['Pro', 'CSV export', 'Export search results and daily lists for your CRM or dialer.'],
    ['Business', 'REST API and webhooks', 'Query entities as JSON with a self-serve API key, and receive webhooks when new filings match your searches.'],
    ['Data license', 'Bulk data feeds', 'Daily or weekly full-feed files (CSV or Parquet) by state or nationally, with a redistribution license available.'],
];
$badge = ['Available now' => 'ok', 'Pro' => '', 'Business' => '', 'Data license' => ''];
?>
<div class="container">
    <div class="page-head">
        <h1>Features</h1>
        <p class="text-muted" style="max-width:62ch">EntityIndex turns scattered state registries into one searchable, up-to-date index of new businesses. Paid features are being rolled out; join the waitlist to be first.</p>
    </div>
    <div class="row g-3">
        <?php foreach ($rows as [$tag, $title, $text]): ?>
            <div class="col-md-6">
                <div class="buyer">
                    <div class="mb-2"><span class="badge-pill-ei <?= $badge[$tag] ?>"><?= Html::encode($tag === 'Available now' ? 'Available now' : 'Coming with ' . $tag) ?></span></div>
                    <h3><?= Html::encode($title) ?></h3>
                    <p><?= Html::encode($text) ?></p>
                </div>
            </div>
        <?php endforeach ?>
    </div>
    <section class="section">
        <div class="card-ei d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div><h2 class="h4 mb-1">See it for yourself</h2><p class="text-muted mb-0">Create a free account and search today’s filings.</p></div>
            <div class="d-flex gap-2">
                <a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-ei">Create free account</a>
                <a href="<?= Url::to(['/pricing/index']) ?>" class="btn btn-outline-secondary">See pricing</a>
            </div>
        </div>
    </section>
</div>
