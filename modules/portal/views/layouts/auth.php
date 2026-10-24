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
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <title><?= Html::encode(($this->title ? $this->title . ' | ' : '') . 'EntityIndex Portal') ?></title>
    <?php $this->head() ?>
</head>
<body class="hold-transition ei-portal <?= $light ? 'ei-light' : 'dark-mode' ?> login-page" data-ei-theme="<?= $theme ?>">
<?= $this->render('_theme-toggle', ['light' => $light, 'class' => 'ei-theme-toggle-corner']) ?>
<?php if ($theme === 'system'): ?><script>eiTheme.sync();</script><?php endif ?>
<?php $this->beginBody() ?>
<div class="login-box">
    <div class="login-logo"><a href="<?= Url::to(['/site/index']) ?>"><?= $this->render('_logo', ['size' => 32]) ?>EntityIndex</a></div>
    <div class="card">
        <div class="card-body login-card-body">
            <?php foreach (Yii::$app->session->getAllFlashes() as $type => $msg): ?>
                <div class="alert alert-<?= $type === 'error' ? 'danger' : Html::encode($type) ?>"><?= Html::encode(is_array($msg) ? implode(' ', $msg) : $msg) ?></div>
            <?php endforeach ?>
            <?= $content ?>
        </div>
    </div>
</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
