<?php

/** @var yii\web\View $this */
/** @var int $today */
/** @var int $jurisdictions */

use app\helpers\Fmt;

$this->title = 'EntityIndex | New US business registrations for agents, every day';
$this->registerMetaTag(['name' => 'description', 'content' => 'Newly registered LLCs and corporations for agents, every day. Join the waitlist.']);
?>
<div class="coming-soon">
    <div class="coming-soon-inner">
        <span class="logo mb-5">EntityIndex</span>
        <p class="eyebrow d-flex">Accounts open soon</p>
        <h1>Reach new businesses the day they register.</h1>
        <p class="lede mt-3">Newly registered LLCs and corporations from US state registries, delivered to your team every day. Accounts open soon.</p>
        <div class="panel raised my-4">
            <table class="ledger">
                <tbody>
                <tr><th scope="row">New companies, last 24 hours</th><td class="num"><?= Fmt::num($today) ?></td></tr>
                <tr><th scope="row">States reporting, last 30 days</th><td class="num"><?= Fmt::num($jurisdictions) ?></td></tr>
                </tbody>
            </table>
        </div>
        <?= $this->render('//_partials/waitlist-form', ['compact' => true]) ?>
        <p class="text-muted small mt-4">For insurance agents, lenders, accountants, agencies, compliance teams and data platforms.</p>
    </div>
</div>
