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
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['description'] ?? 'Newly registered LLCs and corporations from US state registries, searchable within 24 hours of filing, with registered agents and officers.'], 'description');
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => Yii::getAlias('@web/favicon.svg')]);
if (!empty($this->params['canonical'])) {
    $this->registerLinkTag(['rel' => 'canonical', 'href' => Fmt::canonical($this->params['canonical'])]);
}
if (Yii::$app->params['noindex']) {
    $this->registerMetaTag(['name' => 'robots', 'content' => 'noindex,nofollow']);
} elseif (!empty($this->params['noindex'])) {
    $this->registerMetaTag(['name' => 'robots', 'content' => 'noindex,follow']);
}
$title = $this->title ? $this->title . ' | EntityIndex' : 'EntityIndex | New US business registrations, searchable within 24 hours';
$route = Yii::$app->controller->route;
$nav = [['Features', ['/site/features']], ['Pricing', ['/pricing/index']], ['FAQ', ['/site/faq']], ['About', ['/site/about']], ['Contact', ['/site/contact']]];
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
    <nav class="navbar navbar-expand-lg" aria-label="Main">
        <div class="container">
            <a class="logo" href="<?= Url::to(['/site/index']) ?>" aria-label="EntityIndex home">EntityIndex</a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation"><i class="bi bi-list"></i></button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav">
                    <?php foreach ($nav as [$label, $to]): ?>
                        <li class="nav-item"><a class="nav-link<?= $route === ltrim($to[0], '/') ? ' active' : '' ?>" href="<?= Url::to($to) ?>"><?= $label ?></a></li>
                    <?php endforeach ?>
                </ul>
                <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                    <?php if (Yii::$app->user->isGuest): ?>
                        <a class="signin" href="<?= Url::to(['/portal/auth/login']) ?>">Sign in</a>
                        <a class="btn btn-ei btn-sm" href="<?= Url::to(['/portal/auth/register']) ?>">Create account <i class="bi bi-arrow-right"></i></a>
                    <?php else: ?>
                        <a class="btn btn-ei btn-sm" href="<?= Url::to(['/portal/default/index']) ?>">Open portal <i class="bi bi-arrow-right"></i></a>
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
            <div class="col-lg-6">
                <a class="logo" href="<?= Url::to(['/site/index']) ?>">EntityIndex</a>
                <p class="footer-about">Business formation data from US state registries, for agents, lenders, accountants and data teams.</p>
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
            <div class="col-6 col-lg-2">
                <h4>Legal</h4>
                <ul class="list-unstyled">
                    <li><a href="<?= Url::to(['/legal/terms']) ?>">Terms of Service</a></li>
                    <li><a href="<?= Url::to(['/legal/privacy']) ?>">Privacy Policy</a></li>
                    <li><a href="<?= Url::to(['/opt-out/index']) ?>">Remove my information</a></li>
                </ul>
            </div>
        </div>
        <p class="legal mb-0">&copy; <?= date('Y') ?> EntityIndex. Public-record data provided as is, without warranty. Not affiliated with any state agency.</p>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
