<?php

use yii\helpers\Html;

?>
<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:600;letter-spacing:-0.02em;color:#111a4a;">Your details have been removed</h1>
<p style="margin:0 0 16px;">Hi <?= Html::encode($name) ?>,</p>
<p style="margin:0 0 16px;">We have completed your removal request #<?= (int) $ref ?>. The names and addresses of the people listed on this company no longer appear on EntityIndex:</p>
<p style="margin:0 0 20px;padding:14px 16px;border:1px solid #e4e5ec;border-radius:8px;color:#111a4a;"><strong><?= Html::encode($company) ?></strong><br><span style="font-size:13px;color:#6b7089;"><?= Html::encode($state) ?>, filing <?= Html::encode($filing) ?></span></p>
<p style="margin:0 0 16px;">They are hidden from our website, search, API, CSV exports and daily data files, and stay hidden if the state publishes the company again. The company itself, its name, type, status and dates remain listed.</p>
<p style="margin:0;font-size:13px;color:#6b7089;">The record at the state is still public. To change it, file an amendment or a change of registered agent with the state.</p>
