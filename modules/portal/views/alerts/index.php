<?php

use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Alerts';
?>
<?php if (!$user->hasFullAccess()): ?>
    <div class="callout callout-info">
        <h5>Alerts are not part of your plan</h5>
        <p>Save a search, such as new LLCs in Broward County, and get an email every morning with the companies that match. Alerts are included with Pro and above. You are on the <strong><?= Html::encode(ucfirst($user->plan)) ?></strong> plan.</p>
        <a href="<?= Url::to(['/pricing/index', 'plan' => 'pro']) ?>" class="btn btn-primary">See plans</a>
    </div>
<?php else: ?>
    <p class="text-muted">Each saved search sends one email in the morning (<?= Html::encode((string) Yii::$app->params['reportTz']) ?> time) listing the companies that went public since the last one. No new matches, no email. To add a search, filter the <a href="<?= Url::to(['/portal/companies/index']) ?>">Companies</a> page and click <strong>Save search</strong>.</p>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Saved searches (<?= count($searches) ?> of <?= (int) $max ?>)</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>Search</th><th>Email</th><th class="d-none d-md-table-cell">Last email</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($searches as $s): ?>
                    <tr>
                        <td>
                            <a href="<?= Url::to(['/portal/companies/index'] + $s->filters()) ?>" class="font-weight-bold"><?= Html::encode($s->name) ?></a>
                            <div class="text-muted small"><?= Html::encode($s->summary()) ?></div>
                        </td>
                        <td>
                            <?= Html::beginForm(['toggle', 'id' => $s->id], 'post', ['class' => 'd-inline']) ?>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="alert-email-<?= (int) $s->id ?>" <?= $s->email_enabled ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="custom-control-label font-weight-normal" for="alert-email-<?= (int) $s->id ?>"><?= $s->email_enabled ? 'On' : 'Off' ?></label>
                            </div>
                            <?= Html::endForm() ?>
                        </td>
                        <td class="d-none d-md-table-cell small">
                            <?php if ($s->last_sent_at): ?>
                                <?= Html::encode(Fmt::date($s->last_sent_at)) ?><?= $s->last_match_count !== null ? '<div class="text-muted">' . Fmt::num((int) $s->last_match_count) . ' on the last check</div>' : '' ?>
                            <?php else: ?>
                                <span class="text-muted">Not yet</span>
                            <?php endif ?>
                        </td>
                        <td class="text-right text-nowrap">
                            <button type="button" class="btn btn-sm btn-default" data-toggle="modal" data-target="#rename-modal" data-id="<?= (int) $s->id ?>" data-name="<?= Html::encode($s->name) ?>">Rename</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#delete-modal" data-id="<?= (int) $s->id ?>" data-name="<?= Html::encode($s->name) ?>"><i class="fas fa-trash"></i><span class="sr-only">Delete</span></button>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$searches): ?><tr><td colspan="4" class="text-muted">No saved searches yet. Filter the Companies page and click Save search.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="rename-modal" tabindex="-1" role="dialog" aria-labelledby="rename-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <?= Html::beginForm(['update', 'id' => 0], 'post', ['id' => 'rename-form']) ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="rename-modal-title">Rename search</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <label for="rename-name">Name</label>
                    <input type="text" class="form-control" id="rename-name" name="name" maxlength="100" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delete-modal" tabindex="-1" role="dialog" aria-labelledby="delete-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <?= Html::beginForm(['delete', 'id' => 0], 'post', ['id' => 'delete-form']) ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="delete-modal-title">Delete saved search</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Delete <strong id="delete-name"></strong>?</p>
                    <p class="text-muted mb-0">No more emails are sent for it. You can save the same search again later.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete search</button>
                </div>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
    <?php
    $renameUrl = Url::to(['update', 'id' => '__ID__']);
    $deleteUrl = Url::to(['delete', 'id' => '__ID__']);
    $this->registerJs('var renameUrl = ' . json_encode($renameUrl) . ', deleteUrl = ' . json_encode($deleteUrl) . ';' . <<<'JS'
$('#rename-modal').on('show.bs.modal', function (e) { var b = $(e.relatedTarget); $('#rename-form').attr('action', renameUrl.replace('__ID__', b.data('id'))); $('#rename-name').val(b.data('name')); });
$('#rename-modal').on('shown.bs.modal', function () { $('#rename-name').trigger('select'); });
$('#delete-modal').on('show.bs.modal', function (e) { var b = $(e.relatedTarget); $('#delete-form').attr('action', deleteUrl.replace('__ID__', b.data('id'))); $('#delete-name').text(b.data('name')); });
JS);
    ?>
<?php endif ?>
