<?php

/** @var yii\web\View $this */
/** @var app\models\OptOutRequest $request */
/** @var array|null $linked */
/** @var array[] $candidates */
/** @var string $lookup */

use app\helpers\Fmt;
use app\modules\admin\controllers\OptOutsController;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Opt-out request #' . $request->id;
$pending = $request->status === 'pending';
$badge = ['pending' => 'badge-warning', 'done' => 'badge-success', 'rejected' => 'badge-secondary'];
$now = gmdate('Y-m-d H:i:s');
?>
<p><a href="<?= Url::to(['/admin/opt-outs/index']) ?>">&larr; All requests</a></p>
<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Request</h3><div class="card-tools"><span class="badge <?= $badge[$request->status] ?? 'badge-secondary' ?>"><?= Html::encode(OptOutsController::STATUSES[$request->status] ?? $request->status) ?></span></div></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <tbody>
                    <tr><th class="w-25">Name</th><td><?= Html::encode($request->person_name) ?></td></tr>
                    <tr><th>Email</th><td><a href="mailto:<?= Html::encode($request->email) ?>"><?= Html::encode($request->email) ?></a></td></tr>
                    <tr><th>Reference</th><td class="text-break"><?= Html::encode($request->entity_ref) ?></td></tr>
                    <tr><th>What to remove</th><td><?= $request->reason ? nl2br(Html::encode($request->reason)) : '<span class="text-muted">Not given</span>' ?></td></tr>
                    <tr><th>Received</th><td><?= Html::encode(Fmt::dateTime($request->created_at)) ?></td></tr>
                    <?php if (!$pending): ?>
                        <tr><th>Closed</th><td><?= Html::encode($request->resolved_at ? Fmt::dateTime($request->resolved_at) : '') ?></td></tr>
                        <?php if ($request->note): ?><tr><th>Note</th><td><?= nl2br(Html::encode($request->note)) ?></td></tr><?php endif ?>
                    <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if ($pending): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Which company?</h3></div>
                <form action="<?= Url::to(['/admin/opt-outs/view']) ?>" class="card-body border-bottom">
                    <input type="hidden" name="id" value="<?= (int) $request->id ?>">
                    <label for="oo-ref" class="sr-only">Company page URL or filing number</label>
                    <div class="input-group">
                        <input id="oo-ref" class="form-control" name="ref" value="<?= Html::encode($lookup) ?>" placeholder="Company page URL, or state and filing number">
                        <div class="input-group-append"><button class="btn btn-default" type="submit"><i class="fas fa-search"></i> Look up</button></div>
                    </div>
                </form>
                <?= Html::beginForm(['/admin/opt-outs/resolve', 'id' => $request->id], 'post') ?>
                <?php if (!$candidates): ?>
                    <div class="card-body text-muted">No company matches that reference. Change the text above and look up again, or reject the request.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th></th><th>Company</th><th>State</th><th>Filing number</th><th>People</th><th>Visibility</th></tr></thead>
                            <tbody>
                            <?php foreach ($candidates as $i => $c): ?>
                                <tr>
                                    <td><input type="radio" name="entity_id" value="<?= (int) $c['id'] ?>" aria-label="Choose <?= Html::encode($c['name']) ?>"<?= count($candidates) === 1 ? ' checked' : '' ?>></td>
                                    <td class="font-weight-bold"><?= Html::encode(Fmt::na($c['name'])) ?></td>
                                    <td><?= Html::encode($c['state'] === 'NA' ? 'N/A' : $c['state']) ?></td>
                                    <td class="text-muted"><?= Html::encode(Fmt::na($c['state_filing_number'])) ?></td>
                                    <td><?= (int) $c['people_count'] ?><?php if ($c['people_hidden']): ?> <span class="badge badge-secondary">Already hidden</span><?php endif ?></td>
                                    <td><span class="badge <?= $c['publish_at'] !== null && $c['publish_at'] <= $now ? 'badge-success' : 'badge-warning' ?>"><?= $c['publish_at'] !== null && $c['publish_at'] <= $now ? 'Public' : 'Not public' ?></span></td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
                <div class="card-body">
                    <label for="oo-note">Note <span class="text-muted">(optional, kept with the request)</span></label>
                    <textarea id="oo-note" class="form-control" name="note" rows="2" maxlength="500"></textarea>
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between">
                    <button type="submit" class="btn btn-primary" <?= $candidates ? '' : 'disabled' ?>><i class="fas fa-user-slash"></i> Hide all people on the selected company</button>
                    <button type="submit" class="btn btn-default" formaction="<?= Url::to(['/admin/opt-outs/reject', 'id' => $request->id]) ?>" formnovalidate onclick="return confirm('Reject this request?')">Reject</button>
                </div>
                <?= Html::endForm() ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Outcome</h3></div>
                <div class="card-body">
                    <?php if ($request->status === 'done' && $linked): ?>
                        <p>People are hidden on <strong><?= Html::encode($linked['name']) ?></strong> (<?= Html::encode($linked['state']) ?>, filing <?= Html::encode($linked['state_filing_number']) ?>): officers, the registered agent and addresses no longer appear in the portal, search, API, CSV export or bulk files.</p>
                    <?php elseif ($request->status === 'done'): ?>
                        <p>People were hidden on a company that has since been removed. The request stays on record, and the company is hidden again if it comes back.</p>
                    <?php else: ?>
                        <p>This request was rejected. Nothing was changed.</p>
                    <?php endif ?>
                    <p class="text-muted small mb-0">No email is sent from here. Reply to <a href="mailto:<?= Html::encode($request->email) ?>"><?= Html::encode($request->email) ?></a> yourself.</p>
                </div>
                <div class="card-footer">
                    <?= Html::beginForm(['/admin/opt-outs/reopen', 'id' => $request->id], 'post', ['onsubmit' => "return confirm('Reopen this request? Anything it hid will be visible again.')"]) ?>
                    <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-undo"></i> Reopen</button>
                    <?= Html::endForm() ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
