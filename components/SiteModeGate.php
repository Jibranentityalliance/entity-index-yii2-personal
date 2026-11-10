<?php

namespace app\components;

use Yii;
use yii\base\ActionEvent;

/**
 * SITE_MODE=coming_soon shows the waitlist page on every public route.
 * The waitlist/opt-out APIs, the opt-out page, legal pages and the error page stay reachable.
 */
class SiteModeGate
{
    private const ALLOWED = ['site/coming-soon', 'site/error', 'forms/waitlist', 'forms/opt-out', 'opt-out/index', 'legal/privacy', 'legal/terms'];

    public static function handle(ActionEvent $event): void
    {
        if (Yii::$app->params['siteMode'] !== 'coming_soon') {
            return;
        }
        $route = $event->action->getUniqueId();
        if (str_starts_with($route, 'apiv1/')) {
            return;
        }
        if (in_array($route, self::ALLOWED, true)) {
            return;
        }
        $event->isValid = false;
        Yii::$app->response->data = Yii::$app->runAction('site/coming-soon');
    }
}
