<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'API documentation';
$base = Html::encode($baseUrl);
$params = [
    ['q', 'text', 'Matches company name, people names and registered agent name. A filing number also matches exactly.'],
    ['state', '2 letters', 'Two-letter state code, for example FL.'],
    ['type', 'text', 'Entity type, for example LLC.'],
    ['status', 'text', 'Status, for example Active.'],
    ['county', 'text', 'County name.'],
    ['filed_after', 'YYYY-MM-DD', 'Filed on or after this day (inclusive).'],
    ['filed_before', 'YYYY-MM-DD', 'Filed on or before this day (inclusive).'],
    ['order', 'desc | asc', 'desc (default) returns the newest ids first. Use asc to read from the oldest, for example to keep a copy in sync.'],
    ['limit', '1 to 1000', 'Companies per response. Default 100.'],
    ['cursor', 'text', 'The next_cursor value from the previous response.'],
];
$errors = [
    ['400', 'A parameter is invalid. The message says which one.'],
    ['401', 'The key is missing, wrong or revoked.'],
    ['403', 'The account\'s plan does not include API access.'],
    ['404', 'Company or endpoint not found.'],
    ['405', 'Only GET is supported.'],
    ['429', 'Rate limit or monthly quota reached. Wait for the number of seconds in the Retry-After header.'],
];
$fields = [
    ['id', 'number', 'Stable EntityIndex id of the company.'],
    ['state', 'string | null', 'Two-letter state code.'],
    ['filing_number', 'string | null', 'The filing number at the state.'],
    ['name, entity_type, status', 'string | null', 'Legal name, type (LLC, Corporation, ...) and status.'],
    ['filed_at, effective_at, indexed_at', 'ISO 8601 UTC | null', 'When it was filed, when it took effect, and when EntityIndex added it.'],
    ['county, principal_address, mailing_address', 'string | null', 'Location as published.'],
    ['industry', 'object | null', 'Estimated NAICS code and label.'],
    ['registered_agent', 'object | null', 'name, commercial, address and phone.'],
    ['people', 'array', 'Officers, managers and owners: name, role, title, address and phone.'],
    ['address', 'object | null', 'full (one line) plus street, city, state and zip when known.'],
    ['source', 'object', 'url of the official record when known, and pulled_at.'],
    ['filings', 'array', 'Only on the single-company endpoints: type, date and document_url.'],
];
?>
<?php if (!$user->hasApiAccess()): ?>
    <div class="callout callout-info">
        <h5>API access is not part of your plan</h5>
        <p class="mb-2">You can read the documentation, but creating keys needs the Business / API plan or above.</p>
        <a href="<?= Url::to(['/pricing/index']) ?>" class="btn btn-primary btn-sm">See plans</a>
    </div>
<?php else: ?>
    <p><a href="<?= Url::to(['/portal/api-keys/index']) ?>" class="btn btn-primary btn-sm"><i class="fas fa-key"></i> Manage API keys</a></p>
<?php endif ?>

<div class="card">
    <div class="card-header"><h3 class="card-title">Quick start</h3></div>
    <div class="card-body">
        <p>Create a key on the <a href="<?= Url::to(['/portal/api-keys/index']) ?>">API keys</a> page and send it as a Bearer token. The base URL is <code><?= $base ?>/api/v1</code>. Every response is JSON and every endpoint is GET only.</p>
        <pre class="mb-3"><code>curl -H "Authorization: Bearer YOUR_KEY" "<?= $base ?>/api/v1/entities?state=FL&amp;filed_after=2026-09-01&amp;limit=2"</code></pre>
        <p class="mb-0">You can also send the key in an <code>X-API-Key</code> header. Keep it secret: anyone with it can use your quota. If it leaks, revoke it and create a new one.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Endpoints</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table mb-0">
            <thead><tr><th>Request</th><th>Returns</th></tr></thead>
            <tbody>
                <tr><td class="text-nowrap"><code>GET /api/v1/entities</code></td><td>A page of companies, with filters.</td></tr>
                <tr><td class="text-nowrap"><code>GET /api/v1/entities/{id}</code></td><td>One company by its id, including its filings.</td></tr>
                <tr><td class="text-nowrap"><code>GET /api/v1/entities/{state}/{filing_number}</code></td><td>One company by state and state filing number, for example <code>/api/v1/entities/FL/L26000047077</code>.</td></tr>
                <tr><td class="text-nowrap"><code>GET /api/v1/me</code></td><td>Your plan, this month's usage and the key being used.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">List parameters</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table mb-0">
            <thead><tr><th>Parameter</th><th>Value</th><th>Meaning</th></tr></thead>
            <tbody>
            <?php foreach ($params as [$name, $type, $text]): ?>
                <tr><td><code><?= Html::encode($name) ?></code></td><td class="text-nowrap"><?= Html::encode($type) ?></td><td><?= Html::encode($text) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted">All filters are optional and can be combined.</div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Example response</h3></div>
    <div class="card-body">
<pre class="mb-0"><code>{
  "data": [
    {
      "id": 5996,
      "state": "FL",
      "filing_number": "L26000047077",
      "name": "Riverbend Lawn Care LLC",
      "entity_type": "LLC",
      "status": "Active",
      "filed_at": "2026-08-31T13:09:40Z",
      "effective_at": "2026-08-31T00:00:00Z",
      "indexed_at": "2026-08-31T16:54:21Z",
      "county": "Palm Beach",
      "principal_address": "8573 Oak Ave, Boca Raton, FL 33403",
      "mailing_address": "8573 Oak Ave, Boca Raton, FL 33403",
      "industry": { "naics": "561730", "label": "Landscaping Services", "estimated": true },
      "registered_agent": {
        "name": "First Filing Agents LLC",
        "commercial": true,
        "address": { "full": "2654 River Rd, Miami, FL 33163", "street": null, "city": null, "state": null, "zip": null },
        "phone": null
      },
      "people": [
        {
          "name": "Elizabeth Patel",
          "role": "organizer",
          "title": "Organizer",
          "address": { "full": "8573 Oak Ave, Boca Raton, FL 33403", "street": null, "city": null, "state": null, "zip": null },
          "phone": null
        }
      ],
      "source": { "url": null, "pulled_at": "2026-08-31T16:54:21Z" }
    }
  ],
  "has_more": true,
  "next_cursor": "NTk3MQ"
}</code></pre>
    </div>
    <div class="card-footer text-muted">Values that are not on file are <code>null</code>, never an empty string.</div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Fields</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table mb-0">
            <thead><tr><th>Field</th><th>Type</th><th>Meaning</th></tr></thead>
            <tbody>
            <?php foreach ($fields as [$name, $type, $text]): ?>
                <tr><td><code><?= Html::encode($name) ?></code></td><td class="text-nowrap"><?= Html::encode($type) ?></td><td><?= Html::encode($text) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Reading many companies</h3></div>
    <div class="card-body">
        <p>Responses are paged with a cursor, so you can read any number of companies. Ask for up to 1000 at a time, then pass <code>next_cursor</code> back as <code>cursor</code> until <code>has_more</code> is false. Do not build the cursor yourself. New companies arriving while you read do not shift the pages you have already fetched.</p>
<pre class="mb-3"><code>import requests

url = "<?= $base ?>/api/v1/entities"
headers = {"Authorization": "Bearer YOUR_KEY"}
params = {"state": "FL", "filed_after": "2026-09-01", "limit": 1000}

while True:
    page = requests.get(url, headers=headers, params=params).json()
    for company in page["data"]:
        print(company["name"])
    if not page["has_more"]:
        break
    params["cursor"] = page["next_cursor"]</code></pre>
        <p class="mb-0">To keep a copy up to date, store the highest <code>id</code> you have seen. On the next run, read with <code>order=asc</code> and stop at ids you already have, or read newest first and stop when you reach the stored id.</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Errors</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table mb-0">
                    <tbody>
                    <?php foreach ($errors as [$code, $text]): ?>
                        <tr><td><code><?= Html::encode($code) ?></code></td><td><?= Html::encode($text) ?></td></tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted">Errors are JSON with <code>name</code>, <code>message</code> and <code>status</code>.</div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Limits</h3></div>
            <div class="card-body">
                <p>Usage is counted per account, across all your keys, per calendar month in UTC. Each company in a list response counts toward the row limit. When fewer rows are left than you asked for, the response is cut short at the limit.</p>
                <ul>
                    <?php foreach ($limits as $plan => $l): ?>
                        <li><strong><?= Html::encode($plan === 'bulk' ? 'Data license' : 'Business / API') ?></strong>: <?= number_format($l['monthlyCalls']) ?> calls and <?= number_format($l['monthlyRows']) ?> companies returned a month, <?= number_format($l['perMinute']) ?> calls a minute.</li>
                    <?php endforeach ?>
                </ul>
                <p class="mb-2">Every response includes:</p>
                <ul class="mb-0">
                    <li><code>X-RateLimit-Limit</code>, <code>X-RateLimit-Remaining</code>, <code>X-RateLimit-Reset</code> for the per-minute limit.</li>
                    <li><code>X-Quota-Limit</code>, <code>X-Quota-Remaining</code>, <code>X-Quota-Reset</code> for calls in the month.</li>
                    <li><code>X-Rows-Limit</code>, <code>X-Rows-Remaining</code>, <code>X-Rows-Reset</code> for companies returned in the month.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
