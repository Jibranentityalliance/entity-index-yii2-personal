<?php

use yii\helpers\Html;

?>
<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:600;letter-spacing:-0.02em;color:#111a4a;">About your removal request</h1>
<p style="margin:0 0 16px;">Hi <?= Html::encode($name) ?>,</p>
<p style="margin:0 0 16px;">We reviewed your removal request #<?= (int) $ref ?> for:</p>
<p style="margin:0 0 16px;padding:14px 16px;border:1px solid #e4e5ec;border-radius:8px;color:#111a4a;word-break:break-all;"><?= Html::encode($entityRef) ?></p>
<p style="margin:0 0 16px;">We were not able to complete it, usually because we could not match it to a company page or to a person listed on that company. Nothing has been changed.</p>
<p style="margin:0 0 24px;">Reply to this email if you think this is a mistake, or send a new request with the full company page link or the state and filing number.</p>
<p style="margin:0;">
    <a href="<?= Html::encode($formLink) ?>" style="display:inline-block;background:#111a4a;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:8px;">Send a new request</a>
</p>
