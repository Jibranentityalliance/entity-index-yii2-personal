<?php

namespace app\services\bulk;

use Yii;

class Bulk
{
    public static function config(): array
    {
        return (Yii::$app->params['bulk'] ?? []) + ['dir' => '@runtime/bulk', 'sendfile' => 'php', 'keepDays' => 7, 'downloadsPerDay' => 20];
    }

    public static function store(): BulkStore
    {
        $c = self::config();
        return new LocalBulkStore(Yii::getAlias((string) $c['dir']), $c['sendfile'] === 'xsendfile' ? LocalBulkStore::MODE_XSENDFILE : LocalBulkStore::MODE_PHP);
    }
}
