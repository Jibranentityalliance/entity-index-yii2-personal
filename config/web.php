<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'entityindex',
    'name' => 'EntityIndex',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'timeZone' => 'UTC',
    'language' => 'en-US',
    'defaultRoute' => 'site/index',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'modules' => [
        'portal' => [
            'class' => \app\modules\portal\Module::class,
        ],
        'apiv1' => [
            'class' => \app\modules\apiv1\Module::class,
        ],
        'admin' => [
            'class' => \app\modules\admin\Module::class,
        ],
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => envRequired('COOKIE_VALIDATION_KEY'),
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
            'enableAutoLogin' => true,
            'loginUrl' => ['/portal/auth/login'],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => require __DIR__ . '/mail.php',
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                ['class' => \yii\log\FileTarget::class, 'levels' => ['error', 'warning']],
            ],
        ],
        'response' => [
            'on beforeSend' => function ($event) {
                // noindex for every response (HTML, JSON, XML, redirects, errors).
                if (Yii::$app->params['noindex']) {
                    $event->sender->headers->set('X-Robots-Tag', 'noindex, nofollow');
                }
            },
        ],
        'assetManager' => [
            // jQuery and Bootstrap are loaded from CDNs (see assets/AppAsset.php), not from bower/npm packages.
            'bundles' => [
                \yii\web\JqueryAsset::class => [
                    'sourcePath' => null,
                    'basePath' => '@webroot',
                    'baseUrl' => 'https://code.jquery.com',
                    'js' => ['jquery-3.7.1.min.js'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'rules' => [
                '' => 'site/index',
                'features' => 'site/features',
                'faq' => 'site/faq',
                'about' => 'site/about',
                'contact' => 'site/contact',
                'coming-soon' => 'site/coming-soon',
                'pricing' => 'pricing/index',
                'opt-out' => 'opt-out/index',
                'legal/privacy' => 'legal/privacy',
                'legal/terms' => 'legal/terms',
                'api/waitlist' => 'forms/waitlist',
                'api/opt-out' => 'forms/opt-out',
                'api/contact' => 'forms/contact',
                'sitemap.xml' => 'sitemap/index',

                'api/v1' => 'apiv1/default/index',
                'api/v1/me' => 'apiv1/me/index',
                'api/v1/entities' => 'apiv1/entities/index',
                'api/v1/entities/<id:\d+>' => 'apiv1/entities/view',
                'api/v1/entities/<state:[A-Za-z]{2}>/<filing:[A-Za-z0-9-]+>' => 'apiv1/entities/filing',
                'api/v1/<path:.*>' => 'apiv1/default/not-found',

                // Portal (login required)
                'portal' => 'portal/default/index',
                'portal/company/<state:[A-Za-z0-9-]+>/<slug:[A-Za-z0-9-]+>' => 'portal/company/view',
                'portal/<controller:[\w-]+>' => 'portal/<controller>/index',
                'portal/<controller:[\w-]+>/<action:[\w-]+>' => 'portal/<controller>/<action>',

                'admin' => 'admin/default/index',
                'admin/<controller:[\w-]+>' => 'admin/<controller>/index',
                'admin/<controller:[\w-]+>/<action:[\w-]+>' => 'admin/<controller>/<action>',
            ],
        ],
    ],
    // SITE_MODE=coming_soon: every page shows the waitlist page, except the routes in the allowlist.
    'on beforeAction' => [\app\components\SiteModeGate::class, 'handle'],
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = ['class' => \yii\debug\Module::class];
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = ['class' => \yii\gii\Module::class];
}

return $config;
