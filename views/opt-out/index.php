<?php

/** @var yii\web\View $this */
/** @var string $defaultRef */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Remove my information';
$this->params['description'] = 'Request removal of your name or address from an EntityIndex company page.';
$this->params['canonical'] = '/opt-out';
?>
<div class="container">
    <div class="page-head">
        <h1>Remove my information</h1>
        <p class="text-muted">EntityIndex republishes public business filings from state registries. If your name or home address appears on a company page, we will remove the personal details from our site and API. The record at the state stays public; to change it, file an amendment with the state.</p>
    </div>
    <div class="row">
        <div class="col-lg-8">
            <div class="form-panel">
                <form action="<?= Url::to(['/forms/opt-out']) ?>" method="post" data-ajax data-success="Request received. We have emailed you a confirmation and will email again when it is done, usually within 3 business days." class="d-grid gap-3">
                    <div>
                        <label for="o-ref" class="form-label">Company page URL, or state and filing number</label>
                        <input id="o-ref" class="form-control" name="entity_ref" required value="<?= Html::encode($defaultRef) ?>" placeholder="<?= Html::encode(Yii::$app->params['siteUrl']) ?>/company/fl/…">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label for="o-name" class="form-label">Your full name, as listed</label><input id="o-name" class="form-control" name="person_name" required></div>
                        <div class="col-md-6"><label for="o-email" class="form-label">Email for confirmation</label><input id="o-email" class="form-control" name="email" type="email" required></div>
                    </div>
                    <div>
                        <label for="o-reason" class="form-label">What should we remove? <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea id="o-reason" class="form-control" name="reason" rows="3" placeholder="For example, my home address listed as the principal address"></textarea>
                    </div>
                    <div class="alert alert-warning py-2 mb-0 d-none" data-error role="alert"></div>
                    <div><button class="btn btn-ei" type="submit" data-busy="Sending…">Submit request</button></div>
                </form>
            </div>
            <p class="text-muted small mt-3">We never publish Social Security numbers, EINs or personal phone numbers gathered from other sources.</p>
        </div>
    </div>
</div>
