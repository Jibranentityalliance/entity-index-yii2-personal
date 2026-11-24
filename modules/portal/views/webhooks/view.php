<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = $endpoint->description ?: 'Webhook endpoint';
$badge = ['active' => 'badge-success', 'paused' => 'badge-secondary', 'disabled' => 'badge-danger'];
$deliveryBadge = ['pending' => 'badge-warning', 'success' => 'badge-success', 'failed' => 'badge-danger'];
$time = fn(?string $t) => $t ? Html::encode($t) . ' UTC' : '<span class="text-muted">Never</span>';
$post = fn(string $action, string $label, string $class) => Html::beginForm([$action, 'id' => $endpoint->id], 'post', ['class' => 'd-inline'])
    . '<button type="submit" class="btn ' . $class . ' mr-2 mb-2">' . $label . '</button>' . Html::endForm();
$confirmModal = fn(string $id, string $action, string $title, string $body, string $submit, string $submitClass) => '<div class="modal fade" id="' . $id . '" tabindex="-1" role="dialog" aria-labelledby="' . $id . '-title" aria-hidden="true">'
    . '<div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">'
    . Html::beginForm([$action, 'id' => $endpoint->id], 'post')
    . '<div class="modal-header"><h5 class="modal-title" id="' . $id . '-title">' . $title . '</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>'
    . '<div class="modal-body">' . $body . '</div>'
    . '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn ' . $submitClass . '">' . $submit . '</button></div>'
    . Html::endForm() . '</div></div></div>';
?>
<p><a href="<?= Url::to(['index']) ?>"><i class="fas fa-arrow-left"></i> All endpoints</a></p>
<?php if ($endpoint->status === 'disabled'): ?>
    <div class="callout callout-danger">
        <h5>This endpoint was turned off</h5>
        <p class="mb-0"><?= Html::encode($endpoint->disabled_reason ?: 'Too many failed attempts in a row.') ?> Fix your receiver, send a test event, then resume it. Filings that went public in the meantime are kept and sent when you resume.</p>
    </div>
<?php endif ?>
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Endpoint</h3></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">URL</dt><dd class="col-sm-8 text-break"><?= Html::encode($endpoint->url) ?></dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge <?= $badge[$endpoint->status] ?>"><?= Html::encode(ucfirst($endpoint->status)) ?></span><?= $pending ? ' <span class="text-muted small">' . $pending . ' waiting to send</span>' : '' ?></dd>
                    <dt class="col-sm-4">Sends</dt><dd class="col-sm-8"><?= Html::encode($endpoint->filterSummary()) ?></dd>
                    <dt class="col-sm-4">Last success</dt><dd class="col-sm-8"><?= $time($endpoint->last_success_at) ?></dd>
                    <dt class="col-sm-4">Last failure</dt><dd class="col-sm-8"><?= $time($endpoint->last_failure_at) ?><?= $endpoint->failure_count ? ' <span class="text-muted small">' . (int) $endpoint->failure_count . ' in a row</span>' : '' ?></dd>
                    <dt class="col-sm-4">Signing secret</dt>
                    <dd class="col-sm-8">
                        <div class="input-group">
                            <input type="password" class="form-control text-monospace" id="wh-secret" value="<?= Html::encode($endpoint->secret) ?>" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-default" id="wh-reveal">Show</button>
                                <button type="button" class="btn btn-default" id="wh-copy"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </dd>
                </dl>
            </div>
            <div class="card-footer">
                <?= $post('test', '<i class="fas fa-paper-plane"></i> Send test event', 'btn-primary') ?>
                <?= $endpoint->status === 'active' ? $post('pause', '<i class="fas fa-pause"></i> Pause', 'btn-default') : $post('resume', '<i class="fas fa-play"></i> Resume', 'btn-default') ?>
                <button type="button" class="btn btn-default mr-2 mb-2" data-toggle="modal" data-target="#rotate-modal"><i class="fas fa-sync-alt"></i> New secret</button>
                <button type="button" class="btn btn-outline-danger mr-2 mb-2" data-toggle="modal" data-target="#delete-modal"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Recent deliveries</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover">
                    <thead><tr><th>Delivery</th><th>Companies</th><th>Status</th><th>Attempts</th><th>Last answer</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($deliveries as $d): ?>
                        <tr>
                            <td><code>dlv_<?= (int) $d->id ?></code><div class="text-muted small"><?= Html::encode($d->event) ?>, <?= Html::encode($d->created_at) ?> UTC</div></td>
                            <td><?= $d->event === 'test' ? '<span class="text-muted">Sample</span>' : (int) $d->entity_count ?></td>
                            <td>
                                <span class="badge <?= $deliveryBadge[$d->status] ?>"><?= Html::encode(ucfirst($d->status)) ?></span>
                                <?php if ($d->status === 'pending' && $d->next_attempt_at && $d->attempts > 0): ?><div class="text-muted small">Retry at <?= Html::encode($d->next_attempt_at) ?></div><?php endif ?>
                            </td>
                            <td><?= (int) $d->attempts ?></td>
                            <td class="small"><?= $d->response_code ? 'HTTP ' . (int) $d->response_code : '' ?><?= $d->error ? '<div class="text-muted">' . Html::encode($d->error) . '</div>' : '' ?></td>
                            <td class="text-right">
                                <?php if ($d->event !== 'test' && $d->status !== 'pending'): ?>
                                    <?= Html::beginForm(['redeliver', 'id' => $endpoint->id, 'delivery' => $d->id], 'post', ['class' => 'd-inline']) ?>
                                    <button type="submit" class="btn btn-sm btn-default">Send again</button>
                                    <?= Html::endForm() ?>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (!$deliveries): ?><tr><td colspan="6" class="text-muted">Nothing sent yet. New filings that match this endpoint appear here within a minute of going public.</td></tr><?php endif ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted small">Failed deliveries are retried after 1 minute, 5 minutes, 30 minutes, then 2, 6, 12 and 24 hours. After <?= (int) Yii::$app->params['webhooks']['disableAfterFailures'] ?> failed attempts in a row the endpoint is turned off. History is kept for <?= (int) Yii::$app->params['webhooks']['keepDays'] ?> days.</div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Settings</h3></div>
            <?= Html::beginForm(['update', 'id' => $endpoint->id], 'post') ?>
            <div class="card-body">
                <?= $this->render('_form', ['model' => $endpoint]) ?>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-primary">Save</button></div>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
<?= $confirmModal('rotate-modal', 'rotate', 'Replace signing secret',
    '<p class="mb-2">A new secret is created and the current one stops working immediately.</p><p class="text-muted mb-0">Deliveries from now on are signed with the new secret, so update your receiver right after. Until then it will reject them; failed deliveries are retried.</p>',
    'Replace secret', 'btn-primary') ?>
<?= $confirmModal('delete-modal', 'delete', 'Delete endpoint',
    '<p class="mb-2">Delete <strong>' . Html::encode($endpoint->description ?: $endpoint->url) . '</strong>?</p><p class="text-muted mb-0">Nothing more is sent to it, waiting deliveries are dropped and its delivery history is removed. This cannot be undone.</p>',
    'Delete endpoint', 'btn-danger') ?>
<?php $this->registerJs(<<<'JS'
$('#wh-reveal').on('click', function () { var i = document.getElementById('wh-secret'); var show = i.type === 'password'; i.type = show ? 'text' : 'password'; $(this).text(show ? 'Hide' : 'Show'); });
$('#wh-copy').on('click', function () { navigator.clipboard.writeText(document.getElementById('wh-secret').value); $(this).html('Copied'); });
JS) ?>
