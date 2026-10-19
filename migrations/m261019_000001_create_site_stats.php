<?php

use yii\db\Migration;

/**
 * Precomputed numbers for public pages, one JSON document per name. Written by `php yii stats/refresh`
 * (app\services\MirrorStats), read by the home page. computed_at is UTC.
 */
class m261019_000001_create_site_stats extends Migration
{
    public function safeUp()
    {
        $this->createTable('site_stats', [
            'name' => $this->string(64)->notNull(),
            'data' => $this->text()->notNull(),
            'computed_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY (name)',
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function safeDown()
    {
        $this->dropTable('site_stats');
    }
}
