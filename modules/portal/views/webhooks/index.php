<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Webhooks';
$badge = ['active' => 'badge-success', 'paused' => 'badge-secondary', 'disabled' => 'badge-danger'];
?>
<?php if (!$user->hasApiAccess()): ?>
    <div class="callout callout-info">
        <h5>Webhooks are not part of your plan</h5>
        <p>Webhooks are included with the Business / API plan and above. You are on the <strong><?= Html::encode(ucfirst($user->plan)) ?></strong> plan.</p>
        <a href="<?= Url::to(['/pricing/index']) ?>" class="btn btn-primary">See plans</a>
    </div>
<?php else: ?>
    <p class="text-muted">We send new filings to your server as soon as they go public, as a signed JSON <code>POST</code>, in batches of up to <?= (int) Yii::$app->params['webhooks']['batchSize'] ?> companies. Each endpoint can be limited to some states and entity types. See <a href="<?= Url::to(['/portal/api-docs/index', '#' => 'webhooks']) ?>">API docs</a> for the payload and how to check the signature.</p>
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Endpoints (<?= count($endpoints) ?> of <?= (int) $max ?>)</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover">
                        <thead><tr><th>Endpoint</th><th>Sends</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($endpoints as $e): ?>
                            <tr>
                                <td>
                                    <a href="<?= Url::to(['view', 'id' => $e->id]) ?>" class="font-weight-bold text-break"><?= Html::encode($e->description ?: $e->url) ?></a>
                                    <?php if ($e->description): ?><div class="text-muted small text-break"><?= Html::encode($e->url) ?></div><?php endif ?>
                                </td>
                                <td class="small"><?= Html::encode($e->filterSummary()) ?></td>
                                <td><span class="badge <?= $badge[$e->status] ?>"><?= Html::encode(ucfirst($e->status)) ?></span></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if (!$endpoints): ?><tr><td colspan="3" class="text-muted">No endpoints yet. Add one to start receiving new filings.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Add an endpoint</h3></div>
                <?= Html::beginForm(['create'], 'post') ?>
                <div class="card-body">
                    <?= $this->render('_form', ['model' => $model]) ?>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" <?= count($endpoints) >= $max ? 'disabled' : '' ?>><i class="fas fa-plus"></i> Add endpoint</button>
                </div>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
<?php endif ?>
