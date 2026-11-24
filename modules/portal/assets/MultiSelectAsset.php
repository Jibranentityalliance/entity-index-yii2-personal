<?php

namespace app\modules\portal\assets;

use yii\web\AssetBundle;

class MultiSelectAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css',
    ];
    public $js = [
        'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js',
        'js/multiselect.js?v=1',
    ];
    public $depends = [
        PortalAsset::class,
    ];
}
