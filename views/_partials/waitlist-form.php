<?php

/** @var yii\web\View $this */
/** @var string|null $plan */
/** @var bool $compact */

use yii\helpers\Html;
use yii\helpers\Url;

$plan = $plan ?? null;
$compact = $compact ?? false;
?>
<form action="<?= Url::to(['/forms/waitlist']) ?>" method="post" data-ajax data-success="You’re on the list. We’ll email you when accounts open." class="d-grid gap-3">
    <input type="hidden" name="plan" value="<?= Html::encode($plan ?? '') ?>">
    <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="input-group">
        <label for="wl-email" class="visually-hidden">Work email</label>
        <input id="wl-email" class="form-control" name="email" type="email" required placeholder="you@company.com">
        <button class="btn btn-ei" type="submit" data-busy="Joining…">Join waitlist</button>
    </div>
    <?php if (!$compact): ?>
        <div>
            <label for="wl-use" class="form-label">What would you use it for? <span class="text-muted">(optional)</span></label>
            <select id="wl-use" class="form-select" name="use_case">
                <option value="">Choose one</option>
                <?php foreach (['Insurance leads', 'Merchant services / lending', 'Accounting / bookkeeping', 'Web / marketing agency', 'Registered agent / compliance', 'B2B SaaS sales', 'Data platform / AI', 'Other'] as $o): ?>
                    <option><?= Html::encode($o) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    <?php endif ?>
    <div class="alert alert-warning py-2 mb-0 d-none" data-error role="alert"></div>
</form>
