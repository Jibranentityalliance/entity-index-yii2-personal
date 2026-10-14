<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$dbEinSkipTracing = require __DIR__ . '/db_ein_skip_tracing.php';

return [
    'id' => 'entityindex-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'timeZone' => 'UTC',
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => ['class' => \yii\caching\FileCache::class],
        'log' => [
            'targets' => [
                ['class' => \yii\log\FileTarget::class, 'levels' => ['error', 'warning']],
            ],
        ],
        'db' => $db,
        'dbEinSkipTracing' => $dbEinSkipTracing,
        'mutex' => \yii\mutex\FileMutex::class,
    ],
    'params' => $params,
    'controllerMap' => [
        'migrate' => [
            'class' => \yii\console\controllers\MigrateController::class,
            'migrationPath' => '@app/migrations',
        ],
    ],
];
