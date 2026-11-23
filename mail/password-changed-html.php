<?php

use yii\helpers\Html;

?>
<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:600;letter-spacing:-0.02em;color:#111a4a;">Your password was changed</h1>
<p style="margin:0 0 16px;">Hi <?= Html::encode($name) ?>,</p>
<p style="margin:0 0 16px;">The password for your EntityIndex account was changed on <?= Html::encode($when) ?>. You have been signed out on every other device.</p>
<p style="margin:0;">If this was not you, <a href="<?= Html::encode($resetLink) ?>" style="color:#111a4a;font-weight:600;">reset your password now</a> and <a href="<?= Html::encode($contactLink) ?>" style="color:#111a4a;font-weight:600;">let us know</a>.</p>
