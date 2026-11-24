<?php

/** @var yii\web\View $this */
/** @var string|null $plan */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Pricing: alerts, API and bulk data';
$this->params['description'] = 'Search free. Pro alerts and exports from $49/month. API from $299/month. Daily bulk files for data teams.';
$this->params['canonical'] = '/pricing';
$faq = array_slice(require Yii::getAlias('@app/config/faq.php'), 0, 4);
?>
<div class="container">
    <div class="page-head">
        <h1>Pricing</h1>
        <p class="text-muted">Self-serve signup and card payment, with no sales call. Daily data from all 51 jurisdictions on every paid plan.</p>
    </div>

    <div class="plans">
        <?php foreach (Yii::$app->params['tiers'] as $t): ?>
            <div class="plan<?= !empty($t['featured']) ? ' featured' : '' ?>"<?= $t['id'] === 'business' ? ' id="api"' : ($t['id'] === 'bulk' ? ' id="bulk"' : '') ?>>
                <div class="tag"><?= !empty($t['featured']) ? 'Most selected' : '' ?></div>
                <h3><?= Html::encode($t['name']) ?></h3>
                <p class="for"><?= Html::encode($t['for']) ?></p>
                <div class="price"><?= Html::encode($t['price']) ?><small><?= Html::encode($t['per']) ?></small></div>
                <ul><?php foreach ($t['features'] as $f): ?><li><?= Html::encode($f) ?></li><?php endforeach ?></ul>
                <a href="<?= Url::to($t['url']) ?>" class="btn <?= $t['id'] === 'pro' ? 'btn-ei' : 'btn-outline-secondary' ?>"><?= Html::encode($t['cta']) ?></a>
            </div>
        <?php endforeach ?>
    </div>
    <p class="text-muted small mt-3">Add-ons: contact enrichment (phone, email, website, industry) per 1,000 records, and one-off lists such as all new Florida LLCs from the last 30 days.</p>

    <section class="section">
        <div class="row g-5">
        <div class="col-lg-6">
            <h2 class="h4">API preview</h2>
            <p class="text-muted">Every new filing as JSON, within 24 hours. Webhooks push new filings to your server, filtered by state and entity type.</p>
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
        <div class="col-lg-6" id="waitlist">
            <h2 class="h4">Get early access</h2>
            <p class="text-muted">Paid accounts open soon. Join the list and we will email you first<?= $plan ? ' about the ' . Html::encode($plan) . ' plan' : '' ?>.</p>
            <div class="form-panel"><?= $this->render('//_partials/waitlist-form', ['plan' => $plan]) ?></div>
        </div>
        </div>
    </section>

    <section class="section pt-0">
        <div class="section-head d-flex flex-wrap justify-content-between align-items-baseline"><h2 class="mb-0">Questions</h2><a href="<?= Url::to(['/site/faq']) ?>">All questions</a></div>
        <div class="faq prose">
            <?php foreach ($faq as [$cat, $q, $a]): ?>
                <details><summary><?= Html::encode($q) ?></summary><p class="text-muted"><?= Html::encode($a) ?></p></details>
            <?php endforeach ?>
        </div>
    </section>
</div>
