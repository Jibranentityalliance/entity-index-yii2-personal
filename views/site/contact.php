<?php

/** @var yii\web\View $this */
/** @var string $topic */

use app\models\ContactMessage;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Contact us';
$this->params['description'] = 'Questions about EntityIndex, pricing, data licensing or your account? Send us a message.';
$this->params['canonical'] = '/contact';
?>
<div class="container">
    <div class="page-head">
        <h1>Contact us</h1>
        <p class="text-muted">Questions about plans, data licensing or your account? Send us a message and we will reply by email, usually within one business day.</p>
    </div>
    <div class="row">
        <div class="col-lg-8">
            <div class="form-panel">
                <form action="<?= Url::to(['/forms/contact']) ?>" method="post" data-ajax data-success="Thanks, your message is on its way. We will reply by email soon." class="d-grid gap-3">
                    <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label for="c-name" class="form-label">Your name</label><input id="c-name" class="form-control" name="name" required autocomplete="name"></div>
                        <div class="col-md-6"><label for="c-email" class="form-label">Email</label><input id="c-email" class="form-control" name="email" type="email" required autocomplete="email"></div>
                    </div>
                    <div>
                        <label for="c-topic" class="form-label">Topic</label>
                        <select id="c-topic" class="form-select" name="topic">
                            <?php foreach (ContactMessage::TOPICS as $k => $label): ?><option value="<?= $k ?>"<?= $k === $topic ? ' selected' : '' ?>><?= Html::encode($label) ?></option><?php endforeach ?>
                        </select>
                    </div>
                    <div><label for="c-msg" class="form-label">Message</label><textarea id="c-msg" class="form-control" name="message" rows="6" required minlength="10"></textarea></div>
                    <div class="alert alert-warning py-2 mb-0 d-none" data-error role="alert"></div>
                    <div><button class="btn btn-ei" type="submit" data-busy="Sending…">Send message</button></div>
                </form>
            </div>
            <p class="text-muted small mt-3">To remove personal information from a company page, use the <a href="<?= Url::to(['/opt-out/index']) ?>">removal request form</a>.</p>
        </div>
    </div>
</div>
