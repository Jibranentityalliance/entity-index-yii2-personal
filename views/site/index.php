<?php

/** @var yii\web\View $this */
/** @var array|null $stats */

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = null;
$this->params['canonical'] = '/';

$s = $stats ?? [];
$num = fn(string $k) => isset($s[$k]) ? Fmt::num($s[$k]) : 'n/a';
$short = function (string $k) use ($s): string {
    if (!isset($s[$k])) {
        return 'n/a';
    }
    $n = (int) $s[$k];
    return match (true) {
        $n >= 1_000_000 => rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.') . 'M',
        $n >= 100_000 => number_format($n / 1000) . 'K',
        default => Fmt::num($n),
    };
};
$ago = function (?string $iso): string {
    if ($iso === null) {
        return '';
    }
    $min = max(0, intdiv(time() - strtotime($iso), 60));
    return match (true) {
        $min < 1 => 'just now',
        $min < 60 => "$min min ago",
        $min < 48 * 60 => intdiv($min, 60) . ' h ago',
        default => intdiv($min, 1440) . ' d ago',
    };
};
$lag = $s['median_lag_days'] ?? null;
$lagText = $lag === null ? 'n/a' : ($lag === 0 ? 'Same day' : $lag . ($lag === 1 ? ' day' : ' days'));

$daily = array_column($s['daily'] ?? [], 'count');
$spark = '';
if (count($daily) > 1) {
    $max = max(1, max($daily));
    $step = 200 / (count($daily) - 1);
    $pts = [];
    foreach ($daily as $i => $n) {
        $pts[] = round($i * $step, 1) . ',' . round(36 - ($n / $max) * 32, 1);
    }
    $spark = implode(' ', $pts);
}

$record = [
    ['Legal name', 'Example Roofing LLC', 'Free'],
    ['Entity type', 'Limited liability company', 'Free'],
    ['State', 'Florida', 'Free'],
    ['Status', '<span class="status-active">Active</span>', 'Free'],
    ['Registered agent', 'Name and address', 'Free'],
    ['Officers and managers', 'Names, titles, addresses', 'Paid plans'],
    ['Phone numbers', 'Agent and officers', 'Paid plans'],
    ['Source', 'Link to the state record', 'All plans'],
];
$arrow = ' <i class="bi bi-arrow-right"></i>';
?>
<section class="hero">
    <div class="container">
        <div class="row gx-lg-5 align-items-start">
            <div class="col-lg-6 pt-lg-5">
                <p class="eyebrow">New filings, every day</p>
                <h1>The index of every new US business.</h1>
                <p class="lede">EntityIndex collects LLC, corporation and nonprofit filings from state registries and keeps them in one searchable index, with registered agents and officers on every record.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-ei btn-lg">Create an account<?= $arrow ?></a>
                    <a href="<?= Url::to(['/pricing/index']) ?>" class="btn btn-outline-secondary btn-lg">View plans</a>
                </div>
                <p class="meta mb-0"><i class="bi bi-patch-check-fill"></i>Official state sources<span class="text-muted">&middot;</span><?= isset($s['states_30d']) ? Html::encode($num('states_30d')) . ' states reporting' : 'Every record links to the filing' ?></p>
            </div>
            <div class="col-lg-6">
                <div class="hero-visual">
                    <img class="hero-map" src="<?= Yii::getAlias('@web/img/us-dots.svg') ?>" alt="" width="640" height="340">
                    <div class="metric-card hero-metric">
                        <div class="label">New companies, last 24 hours</div>
                        <div class="value"><?= $num('last_24h') ?></div>
                        <?php if ($spark !== ''): ?>
                            <svg viewBox="0 0 200 40" preserveAspectRatio="none" aria-hidden="true"><polyline points="<?= $spark ?>" fill="none" stroke="rgba(255,255,255,.85)" stroke-width="1.6" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>
                        <?php endif ?>
                        <div class="foot"><?= isset($s['computed_at']) ? 'Updated ' . Html::encode($ago($s['computed_at'])) : 'Awaiting first refresh' ?></div>
                    </div>
                    <div class="panel raised feed-card hero-card">
                        <div class="panel-head">Latest additions<span>Type and state</span></div>
                        <?php if (!empty($s['feed'])): ?>
                            <ul aria-label="Latest companies added">
                                <?php foreach (array_slice($s['feed'], 0, 4) as $f): ?>
                                    <li><?= Html::encode($f['type']) ?>, <?= Html::encode(States::name($f['state'])) ?><span><?= Html::encode($ago($f['at'])) ?></span></li>
                                <?php endforeach ?>
                            </ul>
                        <?php else: ?>
                            <div class="panel-foot">Live figures appear here once the first data refresh has run.</div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="stats">
    <div class="container">
        <div class="stats-grid">
            <div class="stat"><div class="n"><?= $short('total') ?></div><div class="l">Companies in the index</div></div>
            <div class="stat"><div class="n"><?= $num('last_7d') ?></div><div class="l">Added in the last 7 days</div></div>
            <div class="stat"><div class="n"><?= $num('states_30d') ?></div><div class="l">States reporting, last 30 days</div></div>
            <div class="stat"><div class="n"><?= Html::encode($lagText) ?></div><div class="l">Median time from state filing</div></div>
        </div>
    </div>
</section>

<section class="section soft">
    <div class="container">
        <div class="row gy-5 gx-lg-5 align-items-start">
            <div class="col-lg-5">
                <p class="eyebrow">The record</p>
                <h2>One record format, in every state.</h2>
                <p class="intro">Every filing becomes a single record with the same fields in all 51 jurisdictions, and every record keeps its filing number and a link back to the state.</p>
                <ul class="checks">
                    <li><i class="bi bi-check2"></i>Free: filing details and the registered agent</li>
                    <li><i class="bi bi-check2"></i>Paid: officers, addresses and phone numbers</li>
                    <li><i class="bi bi-check2"></i>All plans: a link to the official state record</li>
                </ul>
            </div>
            <div class="col-lg-7">
                <div class="panel raised">
                    <div class="panel-body pb-3">
                        <h3 class="h4 mb-1">Example Roofing LLC</h3>
                        <span class="filing-no">L26000123456</span>
                    </div>
                    <table class="ledger" aria-label="Sample record">
                        <tbody>
                        <?php foreach ($record as [$field, $value, $tier]): ?>
                            <tr><th scope="row"><?= Html::encode($field) ?></th><td><?= str_starts_with($value, '<span') ? $value : Html::encode($value) ?></td><td class="tier"><?= Html::encode($tier) ?></td></tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                    <div class="panel-foot">Illustrative record. Company names and people are never shown on this page.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section white">
    <div class="container">
        <div class="row gy-5 gx-lg-5 align-items-center">
            <div class="col-lg-5">
                <p class="eyebrow">For developers</p>
                <h2>Built by engineers, for engineers.</h2>
                <p class="intro">Query the index as JSON with a self-serve API key, page through new filings with a cursor, or take the daily bulk files. Every response carries rate-limit and quota headers.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="<?= Url::to(['/portal/api-docs/index']) ?>" class="btn btn-ei btn-lg">Read the API docs<?= $arrow ?></a>
                    <a href="<?= Url::to(['/pricing/index', '#' => 'bulk']) ?>" class="btn btn-outline-secondary btn-lg">Bulk data files</a>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="code-card">
                    <div class="head"><span><b>GET</b>/api/v1/entities?state=FL&amp;type=LLC</span><span class="ok">200</span></div>
<pre>{
  <span class="k">"state"</span>: <span class="s">"FL"</span>,
  <span class="k">"filing_number"</span>: <span class="s">"L26000123456"</span>,
  <span class="k">"name"</span>: <span class="s">"Coastal Roofing LLC"</span>,
  <span class="k">"entity_type"</span>: <span class="s">"LLC"</span>,
  <span class="k">"status"</span>: <span class="s">"Active"</span>,
  <span class="k">"filed_at"</span>: <span class="s">"2026-11-17T14:02:11Z"</span>,
  <span class="k">"registered_agent"</span>: { "name": "..." },
  <span class="k">"people"</span>: [{ "role": "manager" }]
}</pre>
                    <div class="foot"><span>X-RateLimit-Limit: 120</span><span>X-Quota-Remaining: 24,978</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section soft" id="plans">
    <div class="container">
        <div class="row section-head gy-3 align-items-end">
            <div class="col-lg-7">
                <p class="eyebrow">Plans</p>
                <h2 class="mb-0">Simple plans, no sales call.</h2>
            </div>
            <div class="col-lg-5">
                <p class="intro mb-0">Billing is not live yet. Join the waitlist on a paid plan and we will contact you first.</p>
            </div>
        </div>
        <div class="plans">
            <?php foreach (Yii::$app->params['tiers'] as $t): ?>
                <div class="plan<?= !empty($t['featured']) ? ' featured' : '' ?>">
                    <div class="tag"><?= !empty($t['featured']) ? 'Most selected' : '' ?></div>
                    <h3><?= Html::encode($t['name']) ?></h3>
                    <p class="for"><?= Html::encode($t['for']) ?></p>
                    <div class="price"><?= Html::encode($t['price']) ?><small><?= Html::encode($t['per']) ?></small></div>
                    <ul><?php foreach ($t['features'] as $f): ?><li><?= Html::encode($f) ?></li><?php endforeach ?></ul>
                    <a href="<?= Url::to($t['url']) ?>" class="btn <?= !empty($t['featured']) ? 'btn-ei' : 'btn-outline-secondary' ?>"><?= Html::encode($t['cta']) ?><?= !empty($t['featured']) ? $arrow : '' ?></a>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>
