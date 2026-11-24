<?php

use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;

$more = $total - count($items);
?>
<h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;font-weight:600;letter-spacing:-0.02em;color:#111a4a;"><?= Fmt::num($total) ?> new <?= $total === 1 ? 'company' : 'companies' ?></h1>
<p style="margin:0 0 20px;font-size:14px;color:#6b7089;"><?= Html::encode($search->name) ?> &middot; <?= Html::encode($search->summary()) ?></p>
<p style="margin:0 0 20px;">Hi <?= Html::encode($name) ?>, these companies went public since your last alert<?= $more > 0 ? '. Here are the ' . count($items) . ' most recent filings' : '' ?>.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 24px;">
    <?php foreach ($items as $e): ?>
        <tr>
            <td style="padding:12px 0;border-top:1px solid #e4e5ec;">
                <a href="<?= Html::encode($e['link']) ?>" style="color:#111a4a;font-weight:600;text-decoration:none;"><?= Html::encode($e['name']) ?></a>
                <div style="font-size:13px;color:#6b7089;margin-top:2px;">
                    <?= Html::encode(implode(' · ', array_filter([$e['entity_type'], $e['county'] ? $e['county'] . ' County' : null, States::name($e['state']) ?: $e['state'], 'filed ' . Fmt::date($e['filed_at'])]))) ?>
                </div>
            </td>
        </tr>
    <?php endforeach ?>
</table>
<p style="margin:0 0 24px;">
    <a href="<?= Html::encode($searchLink) ?>" style="display:inline-block;background:#111a4a;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:8px;"><?= $more > 0 ? 'See all ' . Fmt::num($total) . ' in EntityIndex' : 'Open this search' ?></a>
</p>
<p style="margin:0;font-size:13px;color:#6b7089;">
    You get this email each morning when <?= Html::encode($search->name) ?> has new matches.
    <a href="<?= Html::encode($manageLink) ?>" style="color:#6b7089;">Manage alerts</a> or
    <a href="<?= Html::encode($unsubscribeLink) ?>" style="color:#6b7089;">stop emails for this search</a>.
</p>
