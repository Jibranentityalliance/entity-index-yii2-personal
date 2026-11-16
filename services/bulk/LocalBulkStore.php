<?php

namespace app\services\bulk;

use Yii;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class LocalBulkStore implements BulkStore
{
    public const MODE_PHP = 'php';
    public const MODE_XSENDFILE = 'xsendfile';

    public function __construct(private string $dir, private string $mode = self::MODE_PHP)
    {
    }

    public function put(string $key, string $localPath): void
    {
        $dest = $this->path($key);
        FileHelper::createDirectory(dirname($dest), 0750);
        $part = $dest . '.part';
        if (!@rename($localPath, $part)) {
            if (!copy($localPath, $part)) {
                throw new \RuntimeException("Could not copy $localPath to $part");
            }
            unlink($localPath);
        }
        if (!rename($part, $dest)) {
            throw new \RuntimeException("Could not move $part to $dest");
        }
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            unlink($path);
        }
        @rmdir(dirname($path));
    }

    public function send(string $key, string $downloadName): Response
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            throw new NotFoundHttpException('This file is no longer available.');
        }
        $options = ['mimeType' => 'application/gzip'];
        $response = Yii::$app->response;
        return $this->mode === self::MODE_XSENDFILE
            ? $response->xSendFile($path, $downloadName, $options)
            : $response->sendFile($path, $downloadName, $options);
    }

    private function path(string $key): string
    {
        if (preg_match('~^\d{4}-\d{2}-\d{2}/[a-z0-9][a-z0-9.-]*$~', $key) !== 1) {
            throw new \InvalidArgumentException('Invalid bulk file key.');
        }
        return rtrim($this->dir, '/') . '/' . $key;
    }
}
