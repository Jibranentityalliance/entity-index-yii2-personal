<?php

namespace app\modules\portal;

use yii\base\Module as BaseModule;

/**
 * Logged-in area ("portal"), AdminLTE theme. Routes: /portal, /portal/auth/login, /portal/account, ...
 * Access control lives in controllers/PortalController.php (everything requires login except the auth pages).
 */
class Module extends BaseModule
{
    public $controllerNamespace = 'app\modules\portal\controllers';
    public $defaultRoute = 'default';
    public $layout = 'main';

    /** Cookie the theme toggle (web/js/portal.js) writes; layouts read it so the page renders in the chosen theme. */
    public const THEME_COOKIE = 'ei_theme';

    /** The chosen theme: 'dark' (default), 'light' or 'system'. Read raw: it is set by JS, so it is not a validated Yii cookie. */
    public static function themeSetting(): string
    {
        $v = $_COOKIE[self::THEME_COOKIE] ?? '';
        return in_array($v, ['light', 'system'], true) ? $v : 'dark';
    }

    /**
     * The theme to render: 'dark' or 'light'. 'system' renders dark here and portal-theme.js switches to light
     * before the page paints when the device prefers it.
     */
    public static function theme(): string
    {
        return self::themeSetting() === 'light' ? 'light' : 'dark';
    }
}
