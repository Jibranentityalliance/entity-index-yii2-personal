<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'API keys';
$newKey = Yii::$app->session->get('newApiKey');
Yii::$app->session->remove('newApiKey');
?>
<?php if (!$user->hasApiAccess()): ?>
    <div class="callout callout-info">
        <h5>API access is not part of your plan</h5>
        <p>The REST API is included with the Business / API plan and above. You are on the <strong><?= Html::encode(ucfirst($user->plan)) ?></strong> plan.</p>
        <a href="<?= Url::to(['/pricing/index']) ?>" class="btn btn-primary">See plans</a>
    </div>
<?php else: ?>
    <?php if ($newKey): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <h5>Your new API key</h5>
            <p>Copy it now. It is shown only once and cannot be recovered later.</p>
            <div class="d-flex align-items-center">
                <input type="text" class="form-control flex-grow-1 mr-2" id="new-key" value="<?= Html::encode($newKey) ?>" readonly>
                <button type="button" class="btn btn-primary text-nowrap" id="copy-key"><i class="fas fa-copy"></i> Copy</button>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <?php $this->registerJs("$('#copy-key').on('click', function () { var i = document.getElementById('new-key'); i.select(); navigator.clipboard.writeText(i.value); $(this).text('Copied'); });") ?>
    <?php endif ?>

    <div class="card">
        <div class="card-header"><h3 class="card-title">API usage this month</h3></div>
        <div class="card-body">
            <?php if ($usage['limit'] === null): ?>
                <p class="mb-0"><strong><?= number_format($usage['used']) ?></strong> calls and <strong><?= number_format($usage['rowsUsed']) ?></strong> companies returned so far. Your account has no monthly cap.</p>
            <?php else: ?>
                <?php foreach ([['calls', $usage['used'], $usage['limit']], ['companies returned', $usage['rowsUsed'], $usage['rowLimit']]] as [$label, $used, $cap]): ?>
                    <?php $pct = min(100, (int) round($used / $cap * 100)); $tone = $used / $cap >= 0.8 ? 'bg-danger' : ($used / $cap >= 0.5 ? 'bg-warning' : 'bg-success'); ?>
                    <p class="mb-2"><strong><?= number_format($used) ?></strong> of <?= number_format($cap) ?> <?= $label ?> used.</p>
                    <div class="progress rounded-pill mb-3"><div class="progress-bar rounded-pill <?= $tone ?>" role="progressbar" style="width: <?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                <?php endforeach ?>
                <p class="text-muted mb-0">Resets <?= gmdate('M j, Y', $usage['resetsAt']) ?> (UTC).</p>
            <?php endif ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Active keys (<?= count($keys) ?> of <?= (int) $max ?>)</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead><tr><th>Name</th><th>Key</th><th>Created</th><th>Last used</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($keys as $k): ?>
                            <tr>
                                <td><?= Html::encode($k->name) ?></td>
                                <td><code><?= Html::encode($k->key_prefix) ?>...</code></td>
                                <td><?= Html::encode(substr($k->created_at, 0, 10)) ?></td>
                                <td><?= $k->last_used_at ? Html::encode($k->last_used_at) . ' UTC' : 'Never' ?></td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#revoke-modal" data-key-id="<?= (int) $k->id ?>" data-key-name="<?= Html::encode($k->name) ?>" data-key-prefix="<?= Html::encode($k->key_prefix) ?>">Revoke</button>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        <?php if (!$keys): ?><tr><td colspan="5" class="text-muted">No keys yet. Create one to start calling the API.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Create a key</h3></div>
                <?= Html::beginForm(['create'], 'post') ?>
                <div class="card-body">
                    <div class="form-group">
                        <label for="key-name">Name</label>
                        <input type="text" class="form-control" id="key-name" name="name" maxlength="100" placeholder="e.g. Production server">
                    </div>
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary"<?= count($keys) >= $max ? ' disabled' : '' ?>>Create key</button></div>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="revoke-modal" tabindex="-1" role="dialog" aria-labelledby="revoke-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <?= Html::beginForm(['revoke'], 'post') ?>
                <?= Html::hiddenInput('id', '', ['id' => 'revoke-key-id']) ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="revoke-modal-title">Revoke API key</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Revoke <strong id="revoke-key-name"></strong> (<code id="revoke-key-prefix"></code>)?</p>
                    <p class="text-muted mb-0">Anything using this key stops working immediately. This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Revoke key</button>
                </div>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
    <?php $this->registerJs("$('#revoke-modal').on('show.bs.modal', function (e) { var b = $(e.relatedTarget); $('#revoke-key-id').val(b.data('key-id')); $('#revoke-key-name').text(b.data('key-name')); $('#revoke-key-prefix').text(b.data('key-prefix') + '...'); });") ?>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Using the API</h3></div>
        <div class="card-body">
            <p>Send the key as a Bearer token on every request:</p>
            <pre class="mb-3"><code>curl -H "Authorization: Bearer YOUR_KEY" <?= Html::encode($baseUrl) ?>/api/v1/me</code></pre>
            <a href="<?= Url::to(['/portal/api-docs/index']) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-book"></i> Read the API documentation</a>
        </div>
    </div>
<?php endif ?>
