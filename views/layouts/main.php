<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\helpers\Fmt;
use app\helpers\States;
use yii\helpers\Html;
use yii\helpers\Url;

AppAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
if (!isset($this->params['description'])) {
    $this->registerMetaTag(['name' => 'description', 'content' => 'Search every new LLC and corporation from all 50 states and DC within 24 hours of filing. Daily alerts, API and bulk files.'], 'description');
} else {
    $this->registerMetaTag(['name' => 'description', 'content' => $this->params['description']], 'description');
}
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => Yii::getAlias('@web/favicon.svg')]);
if (!empty($this->params['canonical'])) {
    $this->registerLinkTag(['rel' => 'canonical', 'href' => Fmt::canonical($this->params['canonical'])]);
}
if (!empty($this->params['noindex'])) {
    $this->registerMetaTag(['name' => 'robots', 'content' => 'noindex,follow']);
}
$title = $this->title ? $this->title . ' | EntityIndex' : 'EntityIndex — New US business filings, searchable within 24 hours';
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($title) ?></title>
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand logo" href="<?= Url::to(['/site/index']) ?>" aria-label="EntityIndex home">
                <span class="logo-mark" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="2" width="9" height="2.4" rx="1.2" fill="#fbfbf9"/><rect x="1" y="6.8" width="14" height="2.4" rx="1.2" fill="#3fbf8f"/><rect x="1" y="11.6" width="6" height="2.4" rx="1.2" fill="#fbfbf9"/></svg></span>
                EntityIndex
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= Url::to(['/new/index']) ?>">New filings</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Url::to(['/states/index']) ?>">States</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Url::to(['/pricing/index']) ?>">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Url::to(['/pricing/index', '#' => 'api']) ?>">API</a></li>
                </ul>
                <form action="<?= Url::to(['/search/index']) ?>" class="d-flex me-lg-3 my-2 my-lg-0" role="search">
                    <label for="hq" class="visually-hidden">Search companies</label>
                    <input id="hq" class="form-control form-control-sm" name="q" type="search" placeholder="Search company, officer or filing #">
                </form>
                <?php if (Yii::$app->user->isGuest): ?>
                    <a class="btn btn-link btn-sm text-decoration-none me-2" href="<?= Url::to(['/portal/auth/login']) ?>">Sign in</a>
                <?php else: ?>
                    <a class="btn btn-link btn-sm text-decoration-none me-2" href="<?= Url::to(['/portal/default/index']) ?>">Portal</a>
                <?php endif ?>
                <a class="btn btn-ei btn-sm" href="<?= Url::to(['/pricing/index']) ?>">Get alerts</a>
            </div>
        </div>
    </nav>
</header>

<main id="main" class="flex-shrink-0" role="main">
    <?= $content ?>
</main>

<footer class="site-footer mt-auto">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="logo" href="<?= Url::to(['/site/index']) ?>">EntityIndex</a>
                <p class="text-muted small mt-2" style="max-width:320px">New business entities from all 50 states and DC, searchable within 24 hours of filing. Every record links back to the official state source.</p>
            </div>
            <div class="col-6 col-lg-2">
                <h4>Data</h4>
                <ul class="list-unstyled">
                    <li><a href="<?= Url::to(['/new/index']) ?>">New filings today</a></li>
                    <li><a href="<?= Url::to(['/states/index']) ?>">Coverage &amp; freshness</a></li>
                    <li><a href="<?= Url::to(['/search/index']) ?>">Search</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h4>Product</h4>
                <ul class="list-unstyled">
                    <li><a href="<?= Url::to(['/pricing/index']) ?>">Pricing</a></li>
                    <li><a href="<?= Url::to(['/pricing/index', '#' => 'api']) ?>">API</a></li>
                    <li><a href="<?= Url::to(['/pricing/index', '#' => 'bulk']) ?>">Bulk data</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h4>Company</h4>
                <ul class="list-unstyled">
                    <li><a href="<?= Url::to(['/opt-out/index']) ?>">Remove my information</a></li>
                    <li><a href="<?= Url::to(['/legal/terms']) ?>">Terms</a></li>
                    <li><a href="<?= Url::to(['/legal/privacy']) ?>">Privacy</a></li>
                </ul>
            </div>
        </div>
        <nav class="footer-states" aria-label="New businesses by state">
            <?php foreach (States::all() as $s): ?>
                <a href="<?= Fmt::stateUrl($s['code']) ?>"><?= Html::encode($s['name']) ?></a>
            <?php endforeach ?>
        </nav>
        <p class="text-muted small mt-3 mb-0">© <?= date('Y') ?> EntityIndex. Public-record data provided as is, without warranty. Not affiliated with any state agency.</p>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
