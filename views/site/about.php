<?php

/** @var yii\web\View $this */

use yii\helpers\Url;

$this->title = 'About';
$this->params['description'] = 'EntityIndex indexes new US business filings from official state records so teams can reach new businesses first.';
$this->params['canonical'] = '/about';
?>
<div class="container">
    <div class="page-head"><h1>About EntityIndex</h1></div>
    <div class="prose">
        <p class="lead">Every week thousands of new businesses register with a Secretary of State. The records are public, but they are scattered across 51 different websites with 51 different formats. We collect them, standardize them and make them searchable within 24 hours.</p>
        <h2>Where the data comes from</h2>
        <p>We use official sources only: open-data APIs and daily bulk files where states provide them, and the state’s own website where they do not. Each record keeps its state filing number, the time we pulled it, and a link back to the source.</p>
        <h2>What searchable within 24 hours means</h2>
        <p>It is our target for the time between the timestamp on the state record and the moment the record appears here. We publish the measured median for every state on the coverage page inside your account, so you can check that we are meeting it.</p>
        <h2>Public records, handled carefully</h2>
        <p>We republish only what the state publishes. We never publish Social Security numbers, EINs or personal phone numbers gathered from other sources, and anyone can ask us to remove personal details through the <a href="<?= Url::to(['/opt-out/index']) ?>">removal request form</a>.</p>
        <h2>Who uses it</h2>
        <p>Insurance agents, lenders and merchant-services teams, accountants, web and marketing agencies, registered-agent and compliance providers, and data platforms that need a clean feed of new entities.</p>
        <p class="mt-4"><a href="<?= Url::to(['/portal/auth/register']) ?>" class="btn btn-ei">Create an account</a> <a href="<?= Url::to(['/site/contact']) ?>" class="btn btn-outline-secondary ms-2">Contact us</a></p>
    </div>
</div>
