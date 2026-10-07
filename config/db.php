<?php

return [
    'class' => \yii\db\Connection::class,
    'dsn' => env('DB_DSN', 'mysql:host=127.0.0.1;dbname=entityindex'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASS', ''),
    'charset' => 'utf8mb4',
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
