<?php

/** @var yii\web\View $this */
/** @var string|null $plan */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Pricing — alerts, API and bulk data';
$this->params['description'] = 'Search free. Pro alerts and exports from $49/month. API from $299/month. Daily bulk files for data teams.';
$this->params['canonical'] = '/pricing';
$faq = [
    ['Where does the data come from?', 'Directly from each state’s business registry: official open-data APIs and daily bulk files where available, the state website otherwise. Every record shows its state filing number and when we pulled it.'],
    ['How fresh is it?', 'Our target is every new filing searchable within 24 hours of the state record. The Coverage page shows the live median lag for every state.'],
    ['Do you include phone numbers and emails?', 'Records include what the state publishes: names, registered agent and addresses. Contact enrichment will be an optional add-on, and buyers must follow TCPA and CAN-SPAM rules.'],
    ['Can I resell the data?', 'Not on Pro or Business. Redistribution needs a Data license.'],
];
?>
<div class="container">
    <div class="page-head text-center pt-5">
        <h1>Priced for teams, not enterprises</h1>
        <p class="text-muted mx-auto" style="max-width:620px">Self-serve signup, card payment, no sales call. Daily data from all 51 jurisdictions on every paid plan.</p>
    </div>

    <div class="row g-3">
        <?php foreach (Yii::$app->params['tiers'] as $t): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card-ei tier h-100<?= !empty($t['featured']) ? ' featured' : '' ?>"<?= $t['id'] === 'business' ? ' id="api"' : ($t['id'] === 'bulk' ? ' id="bulk"' : '') ?>>
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="h6 m-0"><?= Html::encode($t['name']) ?></h3>
                        <?php if (!empty($t['featured'])): ?><span class="badge-pill-ei ok">Most popular</span><?php endif ?>
                    </div>
                    <div class="price mt-2"><?= Html::encode($t['price']) ?><small><?= Html::encode($t['per']) ?></small></div>
                    <div class="text-muted small"><?= Html::encode($t['for']) ?></div>
                    <ul><?php foreach ($t['features'] as $f): ?><li><?= Html::encode($f) ?></li><?php endforeach ?></ul>
                    <a href="<?= Url::to($t['url']) ?>" class="btn w-100 <?= $t['id'] === 'pro' ? 'btn-ei' : 'btn-outline-secondary' ?>"><?= Html::encode($t['cta']) ?></a>
                </div>
            </div>
        <?php endforeach ?>
    </div>
    <p class="text-muted small text-center mt-3">Add-ons: contact enrichment (phone, email, website, industry) per 1,000 records · one-off lists such as “all new FL LLCs, last 30 days”.</p>

    <section class="section row g-4">
        <div class="col-lg-6">
            <div class="card-ei h-100">
                <h2 class="h4">API preview</h2>
                <p class="text-muted">Every new filing, as JSON, within 24 hours. Webhooks push new matches for your saved searches.</p>
                <pre class="api-sample">GET /v1/entities?state=FL&amp;filed_after=2026-09-28&amp;type=LLC
Authorization: Bearer ei_live_…

{
  "data": [{
    "state": "FL",
    "filing_number": "L26000123456",
    "name": "Coastal Roofing LLC",
    "entity_type": "LLC",
    "status": "Active",
    "filed_at": "2026-09-28T14:02:11Z",
    "indexed_at": "2026-09-28T19:40:03Z",
    "registered_agent": { "name": "…", "commercial": true },
    "people": [{ "name": "…", "role": "manager" }],
    "source": { "url": "…", "pulled_at": "…" }
  }],
  "next_cursor": "…"
}</pre>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card-ei h-100" id="waitlist">
                <h2 class="h4">Get early access</h2>
                <p class="text-muted small">Paid accounts open soon. Join the list and we’ll email you first<?= $plan ? ' about the ' . Html::encode($plan) . ' plan' : '' ?>.</p>
                <?= $this->render('//_partials/waitlist-form', ['plan' => $plan]) ?>
            </div>
        </div>
    </section>

    <section class="section prose">
        <h2>Questions</h2>
        <div class="faq">
            <?php foreach ($faq as [$q, $a]): ?>
                <details><summary><?= Html::encode($q) ?></summary><p class="text-muted"><?= Html::encode($a) ?></p></details>
            <?php endforeach ?>
        </div>
    </section>
</div>
