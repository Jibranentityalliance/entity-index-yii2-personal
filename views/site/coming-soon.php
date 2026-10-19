<?php

/** @var yii\web\View $this */
/** @var int $today */
/** @var int $jurisdictions */

use app\helpers\Fmt;

$this->title = 'EntityIndex — every new US business, searchable within 24 hours';
$this->registerMetaTag(['name' => 'description', 'content' => 'Newly registered LLCs and corporations for agents, every day. Join the waitlist.']);
?>
<div class="coming-soon">
    <div style="max-width:640px;width:100%">
        <div class="d-flex align-items-center gap-2 mb-5"><span class="logo-mark"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="2" width="9" height="2.4" rx="1.2" fill="#fbfbf9"/><rect x="1" y="6.8" width="14" height="2.4" rx="1.2" fill="#3fbf8f"/><rect x="1" y="11.6" width="6" height="2.4" rx="1.2" fill="#fbfbf9"/></svg></span><strong class="fs-5">EntityIndex</strong></div>
        <h1 class="display-5 fw-bold">Every new US business, searchable within 24 hours.</h1>
        <p class="text-muted fs-5">Newly registered LLCs and corporations, delivered to your agents every day.</p>
        <div class="card-ei d-flex flex-wrap gap-4 my-4">
            <div>
                <div class="d-flex align-items-center gap-2"><span class="live-dot"></span><span class="text-muted small">New companies, last 24 hours</span></div>
                <div class="big-num"><?= Fmt::num($today) ?></div>
            </div>
            <div class="ps-4 border-start">
                <div class="text-muted small">States active, last 30 days</div>
                <div class="big-num"><?= $jurisdictions ?></div>
            </div>
        </div>
        <?= $this->render('//_partials/waitlist-form', ['compact' => true]) ?>
        <p class="text-muted small mt-4">For insurance agents, lenders, accountants, agencies, compliance teams and data platforms.</p>
    </div>
</div>
