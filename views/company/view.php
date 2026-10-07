<?php

/** @var yii\web\View $this */
/** @var app\models\Entity $e */
/** @var array $st */
/** @var array[] $related */

use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$row = $e->attributes;
$canonical = Fmt::entityUrl($row);
$where = Fmt::cityState($e->principal_address);
$lag = Fmt::lagHours($e->filed_at, $e->indexed_at);
$officers = array_values(array_filter($e->people, fn($p) => $p->role !== 'agent'));
$agent = $e->agent;
$sourceName = ['FL' => 'Florida Division of Corporations (Sunbiz)', 'DE' => 'Delaware Division of Corporations', 'DC' => 'DC Dept. of Licensing and Consumer Protection'][$e->state] ?? "{$st['name']} Secretary of State";

$this->title = "{$e->name} — {$st['name']} {$e->entity_type}, filed " . Fmt::date($e->filed_at);
$this->params['description'] = "{$e->name} is a {$st['name']} {$e->entity_type} based in " . ($where ?? $st['name']) . ', filed ' . Fmt::date($e->filed_at) . " (filing #{$e->state_filing_number}, status: " . strtolower($e->status) . '). See registered agent, status and filing history.';
$this->params['canonical'] = parse_url($canonical, PHP_URL_PATH);

$jsonLd = array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $e->name,
    'legalName' => $e->name,
    'foundingDate' => $e->formation_date,
    'url' => Fmt::canonical($this->params['canonical']),
    'identifier' => ['@type' => 'PropertyValue', 'propertyID' => "{$e->state} filing number", 'value' => $e->state_filing_number],
    'address' => $where ? ['@type' => 'PostalAddress', 'addressLocality' => explode(',', $where)[0], 'addressRegion' => $e->state, 'addressCountry' => 'US'] : null,
]);
?>
<script type="application/ld+json"><?= Json::htmlEncode($jsonLd) ?></script>
<div class="container">
    <div class="page-head">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="<?= Url::to(['/site/index']) ?>">Home</a><span>/</span>
            <a href="<?= Fmt::stateUrl($st['code']) ?>"><?= Html::encode($st['name']) ?></a><span>/</span>
            <?php if ($e->county): ?><a href="<?= Url::to(['/search/index', 'state' => $st['code'], 'county' => $e->county]) ?>"><?= Html::encode($e->county) ?> County</a><span>/</span><?php endif ?>
            <span aria-current="page"><?= Html::encode($e->name) ?></span>
        </nav>
        <div class="entity-title"><h1><?= Html::encode($e->name) ?></h1> <?= Fmt::statusBadge($e->status) ?></div>
        <p class="text-muted mt-2 mb-0"><?= Html::encode($st['name']) ?> <?= Html::encode($e->entity_type) ?><?= $where ? ' · ' . Html::encode($where) : '' ?> · Filed <?= Fmt::date($e->filed_at) ?></p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <section class="card-ei flush" aria-labelledby="facts">
                <div class="card-head"><h2 id="facts" class="fs-5 m-0">Company details</h2></div>
                <dl class="facts">
                    <dt>Legal name</dt><dd><?= Html::encode($e->name) ?></dd>
                    <dt>State</dt><dd><a href="<?= Fmt::stateUrl($st['code']) ?>"><?= Html::encode($st['name']) ?></a></dd>
                    <dt>Entity type</dt><dd><?= Html::encode($e->entity_type) ?></dd>
                    <dt>Status</dt><dd><?= Html::encode($e->status) ?></dd>
                    <dt>Filing number</dt><dd class="font-monospace"><?= Html::encode($e->state_filing_number) ?></dd>
                    <dt>Formation date</dt><dd><?= Fmt::date($e->formation_date) ?></dd>
                    <dt>Location</dt><dd><?= Html::encode($where ?? '—') ?><?= $e->county ? ' (' . Html::encode($e->county) . ' County)' : '' ?></dd>
                    <?php if ($e->naics_label): ?><dt>Likely industry</dt><dd><?= Html::encode($e->naics_label) ?> <span class="text-muted small">· NAICS <?= Html::encode($e->naics_guess) ?> (estimated)</span></dd><?php endif ?>
                </dl>
                <div class="timeline" aria-label="Filing freshness">
                    <div class="pt"><div class="k">Filed with state</div><div class="t"><?= Fmt::dateTime($e->filed_at) ?></div></div>
                    <div class="bar"><span><?= Fmt::lag($lag) ?></span></div>
                    <div class="pt text-end"><div class="k">Searchable on EntityIndex</div><div class="t"><?= Fmt::dateTime($e->indexed_at) ?></div></div>
                </div>
                <div class="source-box">
                    Source: <?= Html::encode($sourceName) ?>, filing #<?= Html::encode($e->state_filing_number) ?>, pulled <?= Fmt::dateTime($e->indexed_at) ?>.
                    <?php if ($e->source_url): ?><a href="<?= Html::encode($e->source_url) ?>" rel="nofollow noopener" target="_blank">View official record ↗</a>
                    <?php else: ?><span class="text-muted">(Official record link appears here for live data.)</span><?php endif ?>
                </div>
            </section>

            <section class="card-ei flush" aria-labelledby="ra">
                <div class="card-head"><h2 id="ra" class="fs-5 m-0">Registered agent</h2></div>
                <?php if ($agent): ?>
                    <dl class="facts">
                        <dt>Name</dt><dd><?= Html::encode($agent->name) ?> <?php if ($agent->is_commercial_agent): ?><span class="badge-pill-ei">Commercial agent</span><?php endif ?></dd>
                        <dt>Address</dt><dd><?= Html::encode($agent->is_commercial_agent ? $agent->address : (Fmt::cityState($agent->address) ?? '—')) ?></dd>
                    </dl>
                <?php else: ?><div class="empty">No registered agent on file.</div><?php endif ?>
            </section>

            <section class="card-ei flush locked" aria-labelledby="people">
                <div class="card-head"><h2 id="people" class="fs-5 m-0">Officers, managers &amp; addresses</h2><span class="badge-pill-ei"><?= count($officers) ?> on file</span></div>
                <?php foreach ($officers as $p): ?>
                    <div class="locked-row" aria-hidden="true">
                        <span class="blk" style="width:140px"></span>
                        <span class="text-muted small" style="width:120px"><?= Html::encode(Fmt::titleCase($p->title ?: $p->role)) ?></span>
                        <span class="blk" style="width:220px"></span>
                    </div>
                <?php endforeach ?>
                <div class="source-box" style="border-top:0">
                    <span class="text-muted">Filing history: </span>
                    <?php foreach ($e->filings as $i => $f): ?><?= $i ? ' · ' : '' ?><?= Html::encode(Fmt::titleCase($f->filing_type)) ?> (<?= Fmt::date($f->filing_date) ?>)<?php endforeach ?>
                </div>
                <div class="locked-cta">
                    <span style="flex:1;min-width:220px"><strong>Full record with Pro:</strong> officer names, mailing and principal addresses, CSV export and daily alerts for new companies like this one.</span>
                    <a href="<?= Url::to(['/pricing/index', 'plan' => 'pro']) ?>" class="btn btn-ei btn-sm">Unlock with Pro</a>
                </div>
            </section>
        </div>

        <aside class="col-lg-4 d-grid gap-3 align-content-start">
            <div class="card-ei">
                <h3 class="h6">Track new <?= Html::encode($st['name']) ?> companies</h3>
                <p class="text-muted small">Get a daily email of new <?= $e->entity_type === 'LLC' ? 'LLCs' : 'companies' ?><?= $e->county ? ' in ' . Html::encode($e->county) . ' County' : '' ?> — or any search you save.</p>
                <a href="<?= Url::to(['/pricing/index', 'plan' => 'pro']) ?>" class="btn btn-ei w-100">Create an alert</a>
            </div>
            <div class="card-ei small">
                <h3 class="h6">Is this your business?</h3>
                <p class="text-muted mb-0">This page shows public filing data from the state. You can <a href="<?= Url::to(['/opt-out/index', 'ref' => parse_url($canonical, PHP_URL_PATH)]) ?>">request removal of personal information</a>.</p>
            </div>
        </aside>
    </div>

    <?php if ($related): ?>
        <section class="section">
            <div class="section-head">
                <h2>Other new businesses<?= $e->county ? ' in ' . Html::encode($e->county) . ' County' : '' ?>, <?= Html::encode($st['name']) ?></h2>
                <span class="ms-auto"></span>
                <a href="<?= Fmt::stateUrl($st['code']) ?>">All new <?= Html::encode($st['name']) ?> filings →</a>
            </div>
            <div class="card-ei flush"><?= $this->render('//_partials/entity-table', ['items' => $related, 'showState' => false]) ?></div>
        </section>
    <?php endif ?>
</div>
