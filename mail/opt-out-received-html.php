<?php

use yii\helpers\Html;

?>
<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:600;letter-spacing:-0.02em;color:#111a4a;">We received your removal request</h1>
<p style="margin:0 0 16px;">Hi <?= Html::encode($name) ?>,</p>
<p style="margin:0 0 16px;">Thank you. We have your request to remove personal details from an EntityIndex company page. We review every request by hand and will email you again when it is done, usually within 3 business days.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 20px;border:1px solid #e4e5ec;border-radius:8px;font-size:14px;">
    <tr><td style="padding:10px 14px;color:#6b7089;width:40%;border-bottom:1px solid #e4e5ec;">Reference</td><td style="padding:10px 14px;color:#111a4a;border-bottom:1px solid #e4e5ec;">#<?= (int) $ref ?></td></tr>
    <tr><td style="padding:10px 14px;color:#6b7089;border-bottom:1px solid #e4e5ec;">Name as listed</td><td style="padding:10px 14px;color:#111a4a;border-bottom:1px solid #e4e5ec;"><?= Html::encode($personName) ?></td></tr>
    <tr><td style="padding:10px 14px;color:#6b7089;">Company</td><td style="padding:10px 14px;color:#111a4a;word-break:break-all;"><?= Html::encode($entityRef) ?></td></tr>
</table>
<p style="margin:0 0 16px;">Removal hides the names and addresses of people on that company from our website, API, exports and data files. The record at the state stays public; to change it, file an amendment with the state.</p>
<p style="margin:0;font-size:13px;color:#6b7089;">If you did not send this request, you can ignore this email or reply to let us know.</p>
