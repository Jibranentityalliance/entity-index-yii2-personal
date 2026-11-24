<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Features';
$this->params['description'] = 'Search new US business filings, view officers and addresses, export CSV, query the REST API and download daily bulk files.';
$this->params['canonical'] = '/features';
$rows = [
    ['Free', true, 'Search', 'Find companies by name, registered agent or state filing number, and by officer name on paid plans. Results rank an exact filing number first, then names that start with your query.'],
    ['Free', true, 'Filters', 'Narrow any search by state, entity type, status, county and filing date range.'],
    ['Free', true, 'Company records', 'Legal name, entity type, status, filing number, formation date, county, estimated industry (NAICS), registered agent and a link back to the official state record.'],
    ['Free', true, 'Related companies', 'Other new filings in the same state and county, with the same estimated industry shown first.'],
    ['Free', true, 'State coverage', 'Every state with its source, filings by date range, last update and the median time from state filing to searchable.'],
    ['Free', true, 'Usage dashboard', 'Your plan limits and what you have used this month, for lookups, exports and API calls.'],
    ['Pro', true, 'Officers and addresses', 'Officer, manager and member names and titles, with principal and mailing addresses.'],
    ['Pro', true, 'CSV export', 'Export any search, with its filters, as a CSV file for your CRM or dialer, within a monthly row allowance.'],
    ['Business', true, 'REST API', 'Query entities as JSON by state, type, status and filing date, look up a company by state and filing number, and page through results with a cursor.'],
    ['Business', true, 'API keys and quotas', 'Create and revoke your own keys. Every response carries rate-limit and monthly quota headers, and usage shows in your dashboard.'],
    ['Business', true, 'Webhooks', 'Get new filings pushed to your server as signed JSON the moment they go public, filtered by state and entity type, with automatic retries.'],
    ['Business', true, 'API documentation', 'Endpoint reference with request parameters, response fields and examples, inside your account.'],
    ['Data license', true, 'Daily bulk files', 'A national file and one file per state, built every day and downloadable from your account.'],
    ['All plans', true, 'Privacy controls', 'People can ask to have their details removed, and removed details disappear from pages, the API, exports and bulk files.'],
    ['Pro', false, 'Saved-search alerts', 'Save a search such as new LLCs in Broward County and get an email every morning with the new matches.'],

];
?>
<div class="container">
    <div class="page-head">
        <h1>Features</h1>
        <p class="text-muted">EntityIndex turns scattered state registries into one searchable, up-to-date index of new businesses. Billing is not live yet; join the waitlist on a paid plan and we will contact you first.</p>
    </div>
    <div class="panel">
        <table class="ledger">
            <thead><tr><th>Feature</th><th class="d-none d-md-table-cell">Description</th><th>Plan</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as [$plan, $live, $title, $text]): ?>
                <tr>
                    <td><strong><?= Html::encode($title) ?></strong><span class="sub d-md-none"><?= Html::encode($text) ?></span></td>
                    <td class="d-none d-md-table-cell text-muted"><?= Html::encode($text) ?></td>
                    <td class="text-nowrap"><?= Html::encode($plan) ?></td>
                    <td class="text-nowrap"><?= $live ? '<span class="status-active">Available</span>' : '<span class="text-muted">Planned</span>' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <section class="section">
        <div class="cta">
            <div><h2>See it for yourself.</h2><p>Create a free account and search today’s filings.</p></div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-light btn-lg">Create an account</a>
                <a href="<?= Url::to(['/pricing/index']) ?>" class="btn btn-outline-light btn-lg">View pricing</a>
            </div>
        </div>
    </section>
</div>
