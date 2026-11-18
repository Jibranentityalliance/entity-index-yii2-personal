<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\modules\portal\assets\PortalAsset;
use app\modules\portal\Module;
use yii\helpers\Html;
use yii\helpers\Url;

PortalAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1']);
$this->registerMetaTag(['name' => 'robots', 'content' => 'noindex,nofollow']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => Yii::getAlias('@web/favicon.svg')]);

$theme = Module::themeSetting();
$light = Module::theme() === 'light';
$route = Yii::$app->controller->route;
$nav = [
    ['Dashboard', 'fa-tachometer-alt', ['/portal/default/index'], 'portal/default'],
    ['Companies', 'fa-building', ['/portal/companies/index'], 'portal/companies'],
    ['States', 'fa-map', ['/portal/states/index'], 'portal/states'],
    ['API keys', 'fa-key', ['/portal/api-keys/index'], 'portal/api-keys'],
    ['API docs', 'fa-book', ['/portal/api-docs/index'], 'portal/api-docs'],
];
if (Yii::$app->user->identity?->hasBulkAccess()) {
    $nav[] = ['Bulk data', 'fa-download', ['/portal/bulk/index'], 'portal/bulk'];
}
if (Yii::$app->user->identity?->isAdmin()) {
    $nav[] = ['Database', 'fa-database', ['/admin/database/index'], 'admin/database'];
    $nav[] = ['Users', 'fa-users', ['/admin/users/index'], 'admin/users'];
    $nav[] = ['Opt-outs', 'fa-user-slash', ['/admin/opt-outs/index'], 'admin/opt-outs'];
}
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <title><?= Html::encode(($this->title ? $this->title . ' | ' : '') . 'EntityIndex Portal') ?></title>
    <?php $this->head() ?>
</head>
<body class="hold-transition ei-portal <?= $light ? 'ei-light' : 'dark-mode' ?> sidebar-mini layout-fixed" data-ei-theme="<?= $theme ?>">
<?php $this->beginBody() ?>
<div class="wrapper">
    <nav class="main-header navbar navbar-expand <?= $light ? 'navbar-light' : 'navbar-dark' ?>">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle menu"><i class="fas fa-bars"></i></a></li>
        </ul>
        <ul class="navbar-nav ml-auto align-items-center">
            <li class="nav-item"><?= $this->render('_user-menu', ['user' => Yii::$app->user->identity, 'theme' => $theme]) ?></li>
        </ul>
    </nav>

    <aside class="main-sidebar <?= $light ? 'sidebar-light-primary' : 'sidebar-dark-primary' ?>">
        <a href="<?= Url::to(['/portal/default/index']) ?>" class="brand-link"><span class="brand-text">EntityIndex</span></a>
        <div class="sidebar">
            <nav class="mt-3">
                <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
                    <?php foreach ($nav as [$label, $icon, $url, $prefix]): ?>
                        <li class="nav-item"><a href="<?= Url::to($url) ?>" class="nav-link<?= str_starts_with($route, $prefix) ? ' active' : '' ?>"><i class="nav-icon fas <?= $icon ?>"></i><p><?= $label ?></p></a></li>
                    <?php endforeach ?>
                </ul>
            </nav>
        </div>
    </aside>
    <?php if ($theme === 'system'): ?><script>eiTheme.sync();</script><?php endif ?>

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
