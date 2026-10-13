<?php

/** @var yii\web\View $this */
/** @var app\models\Entity $e */
/** @var array $st */
/** @var array[] $related */
/** @var bool $full  paid plan or admin: show officers and addresses */

use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$row = $e->attributes;
$where = Fmt::cityState($e->principal_address);
$lag = Fmt::lagHours($e->filed_at, $e->indexed_at);
$people = array_values(array_filter($e->people, fn($p) => $p->role !== 'agent'));
$agent = $e->agent;
$sourceName = ['FL' => 'Florida Division of Corporations (Sunbiz)', 'DE' => 'Delaware Division of Corporations', 'DC' => 'DC Dept. of Licensing and Consumer Protection'][$e->state] ?? "{$st['name']} Secretary of State";
$this->title = $e->name;
?>
<div class="mb-3">
    <?= Fmt::statusBadgeBs4($e->status) ?>
    <span class="text-muted ml-2"><?= Html::encode($st['name']) ?> <?= Html::encode($e->entity_type) ?><?= $where ? ' · ' . Html::encode($where) : '' ?> · Filed <?= Fmt::date($e->filed_at) ?></span>
    <a class="ml-3" href="<?= Fmt::stateUrl($st['code']) ?>">All new <?= Html::encode($st['name']) ?> filings</a>
    <?php if ($e->county): ?> · <a href="<?= Url::to(['/portal/search/index', 'state' => $st['code'], 'county' => $e->county]) ?>"><?= Html::encode($e->county) ?> County</a><?php endif ?>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Company details</h3></div>
            <div class="card-body p-0">
                <dl class="ei-facts">
                    <dt>Legal name</dt><dd><?= Html::encode($e->name) ?></dd>
                    <dt>State</dt><dd><a href="<?= Fmt::stateUrl($st['code']) ?>"><?= Html::encode($st['name']) ?></a></dd>
                    <dt>Entity type</dt><dd><?= Html::encode($e->entity_type) ?></dd>
                    <dt>Status</dt><dd><?= Html::encode($e->status) ?></dd>
                    <dt>Filing number</dt><dd class="text-monospace"><?= Html::encode($e->state_filing_number) ?></dd>
                    <dt>Formation date</dt><dd><?= Fmt::date($e->formation_date) ?></dd>
                    <dt>Location</dt><dd><?= Html::encode($where ?? '—') ?><?= $e->county ? ' (' . Html::encode($e->county) . ' County)' : '' ?></dd>
                    <?php if ($e->naics_label): ?><dt>Likely industry</dt><dd><?= Html::encode($e->naics_label) ?> <span class="text-muted small">· NAICS <?= Html::encode($e->naics_guess) ?> (estimated)</span></dd><?php endif ?>
                </dl>
                <div class="ei-timeline" aria-label="Filing freshness">
                    <div><div class="k">Filed with state</div><div class="t"><?= Fmt::dateTime($e->filed_at) ?></div></div>
                    <div class="bar"><span><?= Fmt::lag($lag) ?></span></div>
                    <div class="text-right"><div class="k">Searchable on EntityIndex</div><div class="t"><?= Fmt::dateTime($e->indexed_at) ?></div></div>
                </div>
                <div class="ei-source">
                    Source: <?= Html::encode($sourceName) ?>, filing #<?= Html::encode($e->state_filing_number) ?>, pulled <?= Fmt::dateTime($e->indexed_at) ?>.
                    <?php if ($e->source_url): ?><a href="<?= Html::encode($e->source_url) ?>" rel="nofollow noopener" target="_blank">View official record ↗</a>
                    <?php else: ?><span class="text-muted">(Official record link appears here for live data.)</span><?php endif ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Registered agent</h3></div>
            <?php if ($agent): ?>
                <div class="card-body p-0">
                    <dl class="ei-facts">
                        <dt>Name</dt><dd><?= Html::encode($agent->name) ?> <?php if ($agent->is_commercial_agent): ?><span class="badge badge-secondary">Commercial agent</span><?php endif ?></dd>
                        <dt>Address</dt><dd><?= Html::encode($agent->is_commercial_agent || $full ? $agent->address : (Fmt::cityState($agent->address) ?? '—')) ?></dd>
                    </dl>
                </div>
            <?php else: ?><div class="card-body text-muted">No registered agent on file.</div><?php endif ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Officers, managers &amp; addresses</h3>
                <div class="card-tools"><span class="badge badge-secondary"><?= count($people) ?> on file</span></div>
            </div>
            <div class="card-body p-0">
                <?php if ($full): ?>
                    <div class="table-responsive">
                        <table class="table ei-data mb-0">
                            <thead><tr><th>Name</th><th>Role</th><th>Address</th></tr></thead>
                            <tbody>
                            <?php foreach ($people as $p): ?>
                                <tr><td class="font-weight-bold"><?= Html::encode($p->name) ?></td><td><?= Html::encode(Fmt::titleCase($p->title ?: $p->role)) ?></td><td><?= Html::encode($p->address ?? '—') ?></td></tr>
                            <?php endforeach ?>
                            <?php if (!$people): ?><tr><td colspan="3" class="text-muted">No officers on file.</td></tr><?php endif ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="ei-source" style="border-top:0">
                        <span class="text-muted">Mailing address: </span><?= Html::encode($e->mailing_address ?? '—') ?><br>
                        <span class="text-muted">Principal address: </span><?= Html::encode($e->principal_address ?? '—') ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($people as $p): ?>
                        <div class="ei-locked-row" aria-hidden="true">
                            <span class="blk" style="width:140px"></span>
                            <span class="text-muted small mr-3" style="width:120px"><?= Html::encode(Fmt::titleCase($p->title ?: $p->role)) ?></span>
                            <span class="blk" style="width:220px"></span>
                        </div>
                    <?php endforeach ?>
                    <div class="ei-locked-cta">
                        <span><i class="fas fa-lock text-muted"></i> <strong>Full record with Pro:</strong> officer names, mailing and principal addresses, CSV export and daily alerts for new companies like this one.</span>
                        <a href="<?= Url::to(['/pricing/index', 'plan' => 'pro']) ?>" class="btn btn-primary btn-sm">Unlock with Pro</a>
                    </div>
                <?php endif ?>
                <div class="ei-source">
                    <span class="text-muted">Filing history: </span>
                    <?php foreach ($e->filings as $i => $f): ?><?= $i ? ' · ' : '' ?><?= Html::encode(Fmt::titleCase($f->filing_type)) ?> (<?= Fmt::date($f->filing_date) ?>)<?php endforeach ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-outline card-primary">
            <div class="card-body">
                <h3 class="h6">Track new <?= Html::encode($st['name']) ?> companies</h3>
                <p class="text-muted small">Daily email alerts for new <?= $e->entity_type === 'LLC' ? 'LLCs' : 'companies' ?><?= $e->county ? ' in ' . Html::encode($e->county) . ' County' : '' ?>, or any search you save, are coming with Pro.</p>
                <a href="<?= Url::to(['/pricing/index', 'plan' => 'pro', '#' => 'waitlist']) ?>" class="btn btn-primary btn-block">Join the Pro waitlist</a>
            </div>
        </div>
        <div class="card">
            <div class="card-body small">
                <h3 class="h6">Is this your business?</h3>
                <p class="text-muted mb-0">This page shows public filing data from the state. You can <a href="<?= Url::to(['/opt-out/index', 'ref' => Fmt::entityUrl($row)]) ?>">request removal of personal information</a>.</p>
            </div>
        </div>
    </div>
</div>

<?php if ($related): ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Other new businesses<?= $e->county ? ' in ' . Html::encode($e->county) . ' County' : '' ?>, <?= Html::encode($st['name']) ?></h3></div>
        <div class="card-body p-0"><?= $this->render('/_partials/entity-table', ['items' => $related, 'showState' => false]) ?></div>
    </div>
<?php endif ?>
