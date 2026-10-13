<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\modules\portal\assets\PortalAsset;
use yii\helpers\Html;
use yii\helpers\Url;

PortalAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1']);
$this->registerMetaTag(['name' => 'robots', 'content' => 'noindex,nofollow']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => Yii::getAlias('@web/favicon.svg')]);

/** @var app\models\User $user */
$user = Yii::$app->user->identity;
$route = Yii::$app->controller->route;
$nav = [
    ['Dashboard', 'fa-tachometer-alt', ['/portal/default/index'], 'portal/default'],
    ['Search companies', 'fa-search', ['/portal/search/index'], 'portal/search'],
    ['New filings', 'fa-clock', ['/portal/new/index'], 'portal/new'],
    ['Coverage by state', 'fa-map', ['/portal/states/index'], 'portal/states'],
    ['Account', 'fa-user-cog', ['/portal/account/index'], 'portal/account'],
];
$site = [
    ['Pricing & plans', 'fa-tags', ['/pricing/index']],
    ['Help & contact', 'fa-life-ring', ['/site/contact']],
    ['FAQ', 'fa-question-circle', ['/site/faq']],
];
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <title><?= Html::encode(($this->title ? $this->title . ' | ' : '') . 'EntityIndex Portal') ?></title>
    <?php $this->head() ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<?php $this->beginBody() ?>
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle menu"><i class="fas fa-bars"></i></a></li>
            <li class="nav-item d-none d-sm-inline-block"><a href="<?= Url::to(['/site/index']) ?>" class="nav-link">Website</a></li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item d-none d-sm-block"><span class="nav-link text-muted"><?= Html::encode($user->name ?: $user->email) ?></span></li>
            <li class="nav-item">
                <?= Html::beginForm(['/portal/auth/logout'], 'post', ['class' => 'form-inline']) ?>
                <button type="submit" class="btn btn-link nav-link"><i class="fas fa-sign-out-alt"></i> Sign out</button>
                <?= Html::endForm() ?>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="<?= Url::to(['/portal/default/index']) ?>" class="brand-link"><span class="brand-text font-weight-bold pl-3">EntityIndex</span></a>
        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="info">
                    <a href="<?= Url::to(['/portal/account/index']) ?>" class="d-block"><?= Html::encode($user->name ?: $user->email) ?></a>
                    <small class="text-muted"><?= Html::encode(ucfirst($user->plan)) ?> plan<?= $user->isAdmin() ? ' · admin' : '' ?></small>
                </div>
            </div>
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
                    <?php foreach ($nav as [$label, $icon, $url, $prefix]): ?>
                        <li class="nav-item"><a href="<?= Url::to($url) ?>" class="nav-link<?= str_starts_with($route, $prefix) ? ' active' : '' ?>"><i class="nav-icon fas <?= $icon ?>"></i><p><?= $label ?></p></a></li>
                    <?php endforeach ?>
                    <li class="nav-header">HELP</li>
                    <?php foreach ($site as [$label, $icon, $url]): ?>
                        <li class="nav-item"><a href="<?= Url::to($url) ?>" class="nav-link"><i class="nav-icon fas <?= $icon ?>"></i><p><?= $label ?></p></a></li>
                    <?php endforeach ?>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid"><h1 class="h3"><?= Html::encode($this->title) ?></h1></div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <?php foreach (Yii::$app->session->getAllFlashes() as $type => $msg): ?>
                    <div class="alert alert-<?= $type === 'error' ? 'danger' : Html::encode($type) ?> alert-dismissible fade show" role="alert">
                        <?= Html::encode(is_array($msg) ? implode(' ', $msg) : $msg) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endforeach ?>
                <?= $content ?>
            </div>
        </section>
    </div>

    <footer class="main-footer"><small>© <?= date('Y') ?> EntityIndex</small></footer>
</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
