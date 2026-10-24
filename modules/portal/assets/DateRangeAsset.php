<?php

namespace app\modules\portal\assets;

use yii\web\AssetBundle;

/** flatpickr in range mode for the filing-date filter, themed by css/portal.css; js/daterange.js fills the hidden from/to inputs. */
class DateRangeAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css',
    ];
    public $js = [
        'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
        'js/daterange.js?v=1',
    ];
    public $depends = [
        PortalAsset::class,
    ];
}
