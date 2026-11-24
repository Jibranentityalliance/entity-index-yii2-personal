<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Alert emails';
?>
<?php if ($search === null): ?>
    <p class="login-box-msg">Link not recognised</p>
    <p>This link is not valid or the saved search was deleted, so no more emails will be sent for it.</p>
    <p class="mt-3 mb-0"><a href="<?= Url::to(['/portal/alerts/index']) ?>">Manage your alerts</a></p>
<?php elseif ($done): ?>
    <p class="login-box-msg">Emails stopped</p>
    <p>You will no longer get emails for <strong><?= Html::encode($search->name) ?></strong>. The search stays saved, and you can turn emails back on from the Alerts page.</p>
    <p class="mt-3 mb-0"><a href="<?= Url::to(['/portal/alerts/index']) ?>">Manage your alerts</a></p>
<?php else: ?>
    <p class="login-box-msg">Stop alert emails?</p>
    <p>Stop the morning emails for <strong><?= Html::encode($search->name) ?></strong>? Your other saved searches are not affected.</p>
    <?= Html::beginForm(['/portal/alerts/unsubscribe', 'token' => $search->unsubscribe_token], 'post') ?>
    <button type="submit" class="btn btn-primary btn-block">Stop emails for this search</button>
    <?= Html::endForm() ?>
    <p class="mt-3 mb-0"><a href="<?= Url::to(['/portal/alerts/index']) ?>">Manage your alerts instead</a></p>
<?php endif ?>
