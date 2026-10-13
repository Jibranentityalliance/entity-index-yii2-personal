<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

try {
    require __DIR__ . '/../config/env.php';
    envAssertRequired();
} catch (Throwable $e) {
    envFail($e);
}

require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
