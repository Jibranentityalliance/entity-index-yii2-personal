<?php

namespace app\modules\apiv1;

use Yii;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public $controllerNamespace = 'app\modules\apiv1\controllers';
    public $defaultRoute = 'default';

    public function init()
    {
        parent::init();
        Yii::$app->user->enableSession = false;
        Yii::$app->user->loginUrl = null;
        Yii::$app->request->enableCookieValidation = false;
        Yii::$app->request->enableCsrfCookie = false;
    }
}
