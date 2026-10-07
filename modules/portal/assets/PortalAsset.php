<?php

namespace app\modules\portal\assets;

use yii\web\AssetBundle;

/** AdminLTE 3 (bundles Bootstrap 4) loaded from CDNs; jQuery comes from the app-wide JqueryAsset override. */
class PortalAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
        'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css',
    ];
    public $js = [
        'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js',
        'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js',
    ];
    public $depends = [
        \yii\web\YiiAsset::class,
    ];
}
