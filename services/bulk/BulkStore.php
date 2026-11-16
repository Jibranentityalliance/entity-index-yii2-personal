<?php

namespace app\services\bulk;

use yii\web\Response;

interface BulkStore
{
    public function put(string $key, string $localPath): void;

    public function delete(string $key): void;

    public function send(string $key, string $downloadName): Response;
}
