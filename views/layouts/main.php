<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Url;

AppAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
if (!isset($this->params['description'])) {
    $this->registerMetaTag(['name' => 'description', 'content' => 'Newly registered LLCs and corporations, delivered to your agents every day with filing details, registered agents and officers.'], 'description');
} else {
    $this->registerMetaTag(['name' => 'description', 'content' => $this->params['description']], 'description');
}
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => Yii::getAlias('@web/favicon.svg')]);
if (!empty($this->params['canonical'])) {
    $this->registerLinkTag(['rel' => 'canonical', 'href' => Fmt::canonical($this->params['canonical'])]);
}
if (Yii::$app->params['noindex']) {
    $this->registerMetaTag(['name' => 'robots', 'content' => 'noindex,nofollow']); // site-wide switch (SITE_NOINDEX)
} elseif (!empty($this->params['noindex'])) {
    $this->registerMetaTag(['name' => 'robots', 'content' => 'noindex,follow']);    // per-page (search results, legal drafts)
}
$title = $this->title ? $this->title . ' | EntityIndex' : 'EntityIndex — New US businesses for agents, every day';
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
                    <?php foreach ([['Features', ['/site/features']], ['Pricing', ['/pricing/index']], ['FAQ', ['/site/faq']], ['About', ['/site/about']], ['Contact', ['/site/contact']]] as [$label, $route]): ?>
                        <li class="nav-item"><a class="nav-link<?= Yii::$app->controller->route === ltrim($route[0], '/') ? ' active' : '' ?>" href="<?= Url::to($route) ?>"><?= $label ?></a></li>
                    <?php endforeach ?>
                </ul>
                <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                    <?php if (Yii::$app->user->isGuest): ?>
                        <a class="btn btn-link btn-sm text-decoration-none" href="<?= Url::to(['/portal/auth/login']) ?>">Sign in</a>
                        <a class="btn btn-ei btn-sm" href="<?= Url::to(['/portal/auth/register']) ?>">Get started</a>
                    <?php else: ?>
                        <a class="btn btn-ei btn-sm" href="<?= Url::to(['/portal/default/index']) ?>">Open portal</a>
                    <?php endif ?>
                </div>
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
                <p class="text-muted small mt-2" style="max-width:340px">Newly registered US businesses for agents and sales teams, updated every day.</p>
            </div>
            <div class="col-6 col-lg-2">
                <h4>Product</h4>
                <ul class="list-unstyled">
                    <li><a href="<?= Url::to(['/site/features']) ?>">Features</a></li>
                    <li><a href="<?= Url::to(['/pricing/index']) ?>">Pricing</a></li>
                    <li><a href="<?= Url::to(['/portal/auth/register']) ?>">Create account</a></li>
                    <li><a href="<?= Url::to(['/portal/auth/login']) ?>">Sign in</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h4>Company</h4>
                <ul class="list-unstyled">
                    <li><a href="<?= Url::to(['/site/about']) ?>">About</a></li>
                    <li><a href="<?= Url::to(['/site/faq']) ?>">FAQ</a></li>
                    <li><a href="<?= Url::to(['/site/contact']) ?>">Contact</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h4>Legal</h4>
                <ul class="list-unstyled">
                    <li><a href="<?= Url::to(['/legal/terms']) ?>">Terms of Service</a></li>
                    <li><a href="<?= Url::to(['/legal/privacy']) ?>">Privacy Policy</a></li>
                    <li><a href="<?= Url::to(['/opt-out/index']) ?>">Remove my information</a></li>
                </ul>
            </div>
        </div>
        <p class="text-muted small mt-4 mb-0">© <?= date('Y') ?> EntityIndex. Public-record data provided as is, without warranty. Not affiliated with any state agency.</p>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
