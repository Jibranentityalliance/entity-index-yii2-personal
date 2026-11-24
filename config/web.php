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
        'urlManager' => require __DIR__ . '/url.php',
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
