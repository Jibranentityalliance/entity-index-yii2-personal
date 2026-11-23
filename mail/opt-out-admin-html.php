<?php

use yii\helpers\Html;

?>
<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:600;letter-spacing:-0.02em;color:#111a4a;">New removal request #<?= (int) $request->id ?></h1>
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 24px;border:1px solid #e4e5ec;border-radius:8px;font-size:14px;">
    <tr><td style="padding:10px 14px;color:#6b7089;width:35%;border-bottom:1px solid #e4e5ec;">Name</td><td style="padding:10px 14px;color:#111a4a;border-bottom:1px solid #e4e5ec;"><?= Html::encode($request->person_name) ?></td></tr>
    <tr><td style="padding:10px 14px;color:#6b7089;border-bottom:1px solid #e4e5ec;">Email</td><td style="padding:10px 14px;color:#111a4a;border-bottom:1px solid #e4e5ec;"><?= Html::encode($request->email) ?></td></tr>
    <tr><td style="padding:10px 14px;color:#6b7089;border-bottom:1px solid #e4e5ec;">Company</td><td style="padding:10px 14px;color:#111a4a;border-bottom:1px solid #e4e5ec;word-break:break-all;"><?= Html::encode($request->entity_ref) ?></td></tr>
    <tr><td style="padding:10px 14px;color:#6b7089;">What to remove</td><td style="padding:10px 14px;color:#111a4a;"><?= $request->reason ? nl2br(Html::encode($request->reason)) : '<span style="color:#9a9db0;">Not given</span>' ?></td></tr>
</table>
<p style="margin:0;">
    <a href="<?= Html::encode($link) ?>" style="display:inline-block;background:#111a4a;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:8px;">Review the request</a>
</p>
