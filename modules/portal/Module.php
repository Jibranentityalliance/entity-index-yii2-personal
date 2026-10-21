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

    /** 'dark' (default) or 'light'. Read raw: it is set by JS, so it is not a validated Yii cookie. */
    public static function theme(): string
    {
        return ($_COOKIE[self::THEME_COOKIE] ?? '') === 'light' ? 'light' : 'dark';
    }
}
