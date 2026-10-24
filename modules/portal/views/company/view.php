<?php

/** @var yii\web\View $this */
/** @var app\models\Entity $e */
/** @var array $st */
/** @var array[] $related */
/** @var bool $full  paid plan or admin: registered agent, officers, addresses and phones */

use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Url;

$row = $e->attributes;
$where = Fmt::cityState($e->principal_address);
$agents = array_values(array_filter($e->people, fn($p) => $p->role === 'agent'));
$people = array_values(array_filter($e->people, fn($p) => $p->role !== 'agent'));
$legacyAgent = $e->agent; // sample/seeded data keeps the agent in registered_agents
$hasState = $st['code'] !== 'NA';
$sourceName = ['FL' => 'Florida Division of Corporations (Sunbiz)', 'DE' => 'Delaware Division of Corporations', 'DC' => 'DC Dept. of Licensing and Consumer Protection'][$e->state] ?? ($hasState ? "{$st['name']} Secretary of State" : 'State registry');
$this->title = Fmt::na($e->name);
$na = fn(?string $v) => Html::encode(Fmt::na($v));
$upgradeUrl = Url::to(['/pricing/index', 'plan' => 'pro']);

/** Phone cell: masked until clicked (paid plans only; free users never receive the number). */
$phone = function (?string $v): string {
    if ($v === null || trim($v) === '') {
        return '<span class="text-muted">N/A</span>';
    }
    return '<span class="ei-phone">' . Html::encode(Fmt::phoneMasked($v)) . '</span> '
        . '<button type="button" class="btn btn-link btn-sm p-0 ml-1 js-reveal" data-phone="' . Html::encode(Fmt::phone($v)) . '">Show</button>';
};
$this->registerCss('.ei-blur{display:inline-block;height:11px;border-radius:6px;background:var(--ei-border-input);filter:blur(1.5px);vertical-align:middle}'
    . '.ei-locked span{display:block;height:12px;border-radius:6px;background:var(--ei-border-input);filter:blur(1.5px);margin-bottom:10px}.ei-phone{font-variant-numeric:tabular-nums}');
$this->registerJs(<<<'JS'
document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.js-reveal');
    if (!b) return;
    var s = b.previousElementSibling;
    s.textContent = b.dataset.phone;
    s.classList.add('font-weight-bold');
    var a = document.createElement('a');
    a.href = 'tel:' + b.dataset.phone.replace(/[^0-9+]/g, '');
    a.textContent = 'Call';
    a.className = 'btn btn-link btn-sm p-0 ml-1';
    b.replaceWith(a);
});
JS);
?>
<div class="mb-3">
    <?php if ($e->status !== ''): ?><?= Fmt::statusBadgeBs4($e->status) ?><?php endif ?>
    <span class="text-muted ml-2"><?= Html::encode($st['name']) ?> · <?= $na($e->entity_type) ?><?= $where ? ' · ' . Html::encode($where) : '' ?> · Filed <?= $e->filed_at ? Fmt::date($e->filed_at) : 'N/A' ?></span>
    <?php if ($hasState): ?><a class="ml-3" href="<?= Fmt::stateUrl($st['code']) ?>">All <?= Html::encode($st['name']) ?> companies</a><?php endif ?>
    <?php if ($e->county): ?> · <a href="<?= Url::to(['/portal/companies/index', 'state' => $st['code'], 'county' => $e->county]) ?>"><?= Html::encode($e->county) ?> County</a><?php endif ?>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Company details</h3></div>
            <div class="card-body p-0">
                <dl class="row mb-0 px-3 pt-3">
                    <dt class="col-sm-4 text-muted font-weight-normal">Legal name</dt><dd class="col-sm-8"><?= $na($e->name) ?></dd>
                    <dt class="col-sm-4 text-muted font-weight-normal">State</dt><dd class="col-sm-8"><?php if ($hasState): ?><a href="<?= Fmt::stateUrl($st['code']) ?>"><?= Html::encode($st['name']) ?></a><?php else: ?>N/A<?php endif ?></dd>
                    <dt class="col-sm-4 text-muted font-weight-normal">Entity type</dt><dd class="col-sm-8"><?= $na($e->entity_type) ?></dd>
                    <dt class="col-sm-4 text-muted font-weight-normal">Status</dt><dd class="col-sm-8"><?= $na($e->status) ?></dd>
                    <dt class="col-sm-4 text-muted font-weight-normal">Filing number</dt><dd class="col-sm-8 text-monospace"><?= $na($e->state_filing_number) ?></dd>
                    <dt class="col-sm-4 text-muted font-weight-normal">Filed with the state</dt><dd class="col-sm-8"><?= $e->filed_at ? Fmt::date($e->filed_at) : 'N/A' ?></dd>
                    <dt class="col-sm-4 text-muted font-weight-normal">Effective date</dt><dd class="col-sm-8"><?= $e->formation_date ? Fmt::date($e->formation_date) : 'N/A' ?></dd>
                    <dt class="col-sm-4 text-muted font-weight-normal">Added to EntityIndex</dt><dd class="col-sm-8"><?= Fmt::dateTime($e->indexed_at) ?></dd>
                    <?php if ($e->naics_label): ?>
                        <dt class="col-sm-4 text-muted font-weight-normal">Likely industry</dt><dd class="col-sm-8"><?= Html::encode($e->naics_label) ?> <span class="text-muted small">· NAICS <?= Html::encode($e->naics_guess) ?> (estimated)</span></dd>
                    <?php endif ?>
                </dl>
                <div class="card-footer text-muted small">
                    Source: <?= Html::encode($sourceName) ?>, filing #<?= $na($e->state_filing_number) ?>.
                    <?php if ($e->source_url): ?><a href="<?= Html::encode($e->source_url) ?>" rel="nofollow noopener" target="_blank">View official record ↗</a><?php endif ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Registered agent</h3><?php if (!$full): ?><div class="card-tools"><span class="badge badge-warning">Pro</span></div><?php endif ?></div>
            <?php if (!$full): ?>
                <div class="card-body">
                    <div class="ei-locked"><span></span><span class="w-50"></span></div>
                    <div class="callout callout-warning mb-0 mt-3">
                        <h5 class="h6"><i class="fas fa-lock"></i> Registered agent details are a Pro feature</h5>
                        <p class="mb-2 small">See who represents this business, with full address and phone number.</p>
                        <a href="<?= $upgradeUrl ?>" class="btn btn-primary btn-sm text-white text-decoration-none">Upgrade to Pro</a>
                    </div>
                </div>
            <?php elseif ($agents): ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Name</th><th>Address</th><th>Phone</th></tr></thead>
                        <tbody>
                        <?php foreach ($agents as $a): ?>
                            <tr><td class="font-weight-bold"><?= $na($a->name) ?></td><td><?= $na($a->address) ?></td><td class="text-nowrap"><?= $phone($a->phone) ?></td></tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($legacyAgent): ?>
                <div class="card-body p-0">
                    <dl class="row mb-0 px-3 pt-3">
                        <dt class="col-sm-4 text-muted font-weight-normal">Name</dt><dd class="col-sm-8"><?= $na($legacyAgent->name) ?></dd>
                        <dt class="col-sm-4 text-muted font-weight-normal">Address</dt><dd class="col-sm-8"><?= $na($legacyAgent->address) ?></dd>
                        <dt class="col-sm-4 text-muted font-weight-normal">Phone</dt><dd class="col-sm-8 text-muted">N/A</dd>
                    </dl>
                </div>
            <?php else: ?>
                <div class="card-body text-muted">N/A</div>
            <?php endif ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Officers &amp; owners</h3>
                <div class="card-tools"><span class="badge badge-secondary"><?= count($people) ?> on file</span><?php if (!$full): ?> <span class="badge badge-warning">Pro</span><?php endif ?></div>
            </div>
            <?php if ($full): ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Name</th><th>Title</th><th>Address</th><th>Phone</th></tr></thead>
                        <tbody>
                        <?php foreach ($people as $p): ?>
                            <tr><td class="font-weight-bold"><?= $na($p->name) ?></td><td><?= Html::encode($p->title ? Fmt::titleCase($p->title) : 'N/A') ?></td><td><?= $na($p->address) ?></td><td class="text-nowrap"><?= $phone($p->phone ?? null) ?></td></tr>
                        <?php endforeach ?>
                        <?php if (!$people): ?><tr><td colspan="4" class="text-muted">N/A</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <table class="table mb-0">
                    <thead><tr><th>Name</th><th>Title</th><th>Address</th><th>Phone</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($people ?: [null, null], 0, 3) as $p): ?>
                        <tr aria-hidden="true"><td><span class="ei-blur" style="width:110px"></span></td><td><?= $p ? Html::encode($p->title ? Fmt::titleCase($p->title) : 'Officer') : '<span class="ei-blur" style="width:60px"></span>' ?></td><td><span class="ei-blur" style="width:170px"></span></td><td><span class="ei-blur" style="width:90px"></span></td></tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <div class="callout callout-warning m-3">
                    <h5 class="h6"><i class="fas fa-lock"></i> Officers, addresses and phone numbers are a Pro feature</h5>
                    <p class="mb-2 small">Upgrade to see who owns and runs this business and how to reach them.</p>
                    <a href="<?= $upgradeUrl ?>" class="btn btn-primary btn-sm text-white text-decoration-none">Upgrade to Pro</a>
                </div>
            <?php endif ?>
            <?php if ($e->filings): ?>
                <div class="card-footer text-muted small">
                    <span class="text-muted">Filing history: </span>
                    <?php foreach ($e->filings as $i => $f): ?><?= $i ? ' · ' : '' ?><?= Html::encode(Fmt::titleCase($f->filing_type)) ?> (<?= Fmt::date($f->filing_date) ?>)<?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if (!$full): ?>
            <div class="card">
                <div class="card-body">
                    <h3 class="h6">Get the full record</h3>
                    <p class="text-muted small">Pro shows the registered agent, officers, addresses and phone numbers for every company.</p>
                    <a href="<?= $upgradeUrl ?>" class="btn btn-primary btn-block">Upgrade to Pro</a>
                </div>
            </div>
        <?php endif ?>
        <div class="card">
            <div class="card-body small">
                <h3 class="h6">Is this your business?</h3>
                <p class="text-muted mb-0">You can <a href="<?= Url::to(['/opt-out/index', 'ref' => Fmt::entityUrl($row)]) ?>">request removal of personal information</a>.</p>
            </div>
        </div>
    </div>
</div>

<?php if ($related): ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Other new businesses<?= $e->county ? ' in ' . Html::encode($e->county) . ' County' : '' ?><?= $hasState ? ', ' . Html::encode($st['name']) : '' ?></h3></div>
        <div class="card-body p-0"><?= $this->render('/_partials/entity-table', ['items' => $related, 'showState' => false]) ?></div>
    </div>
<?php endif ?>
