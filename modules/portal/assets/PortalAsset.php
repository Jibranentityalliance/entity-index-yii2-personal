<?php

namespace app\modules\portal\assets;

use yii\web\AssetBundle;

/** AdminLTE 3 (bundles Bootstrap 4) loaded from CDNs, themed by css/portal.css (light / dark / system: js/portal-theme.js, js/portal.js); jQuery comes from the app-wide JqueryAsset override. */
class PortalAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
        'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap',
        'css/portal.css?v=11',
    ];
    public $js = [
        'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js',
        'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js',
        'js/portal.js?v=2',
    ];
    public $depends = [
        \yii\web\YiiAsset::class,
        PortalThemeAsset::class,
    ];
}
