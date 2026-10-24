<?php

namespace app\modules\portal\assets;

use yii\web\AssetBundle;
use yii\web\View;

/** js/portal-theme.js in <head>, so the layouts can apply the "system" theme before the page paints. */
class PortalThemeAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $js = ['js/portal-theme.js?v=1'];
    public $jsOptions = ['position' => View::POS_HEAD];
}
