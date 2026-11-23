<?php

use yii\helpers\Html;

?>
<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:600;letter-spacing:-0.02em;color:#111a4a;">Reset your password</h1>
<p style="margin:0 0 16px;">Hi <?= Html::encode($name) ?>,</p>
<p style="margin:0 0 24px;">We received a request to reset the password for your EntityIndex account. Use the button below to choose a new one. The link works once and expires in <?= (int) $minutes ?> minutes.</p>
<p style="margin:0 0 24px;">
    <a href="<?= Html::encode($link) ?>" style="display:inline-block;background:#111a4a;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:8px;">Choose a new password</a>
</p>
<p style="margin:0 0 8px;font-size:13px;color:#6b7089;">If the button does not work, copy this link into your browser:</p>
<p style="margin:0 0 24px;font-size:13px;word-break:break-all;"><a href="<?= Html::encode($link) ?>" style="color:#111a4a;"><?= Html::encode($link) ?></a></p>
<p style="margin:0;font-size:13px;color:#6b7089;">If you did not ask for this, you can ignore this email. Your password stays the same.</p>
