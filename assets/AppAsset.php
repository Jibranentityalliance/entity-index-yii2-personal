<?php

namespace app\assets;

use yii\web\AssetBundle;

/** Public site: Bootstrap 5 (CDN) + jQuery (CDN, via the JqueryAsset override in config/web.php) + site.css/site.js. */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        'css/site.css?v=4',
    ];
    public $js = [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        'js/site.js?v=1',
    ];
    public $depends = [
        \yii\web\YiiAsset::class,
    ];
}
