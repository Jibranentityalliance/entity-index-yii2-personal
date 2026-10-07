<?php

/** @var yii\web\View $this */

use yii\helpers\Url;

$this->title = 'Privacy Policy';
$this->params['noindex'] = true;
?>
<div class="container prose">
    <div class="page-head"><h1>Privacy Policy</h1></div>
    <div class="alert alert-warning">Draft outline, pending legal review. Not yet in effect.</div>
    <h2>Topics the final policy needs to cover</h2>
    <ul>
        <li>What public-record data we republish (names and addresses from state filings) and where it comes from.</li>
        <li>How to request removal: <a href="<?= Url::to(['/opt-out/index']) ?>">opt-out form</a>, response time, and what stays public at the state.</li>
        <li>What we never publish: SSNs, EINs, or personal phone numbers gathered from other sources.</li>
        <li>Account data we collect from customers, cookies and analytics.</li>
    </ul>
</div>
