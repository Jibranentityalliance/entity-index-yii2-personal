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
}
