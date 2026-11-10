<?php

use yii\db\Migration;

class m261105_000002_create_api_usage extends Migration
{
    private const OPTS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function safeUp()
    {
        $this->createTable('api_usage', [
            'user_id' => $this->bigInteger()->unsigned()->notNull(),
            'bucket' => $this->string(20)->notNull(),
            'calls' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'PRIMARY KEY (user_id, bucket)',
        ], self::OPTS);
        $this->addForeignKey('api_usage_user_fk', 'api_usage', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('api_usage');
    }
}
