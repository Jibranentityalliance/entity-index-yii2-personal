<?php

use yii\db\Migration;

class m261105_000001_create_api_key extends Migration
{
    private const OPTS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function safeUp()
    {
        $this->createTable('api_key', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->bigInteger()->unsigned()->notNull(),
            'name' => $this->string(100)->notNull(),
            'key_prefix' => $this->string(16)->notNull(),
            'key_hash' => $this->char(64)->notNull(),
            'last_used_at' => $this->dateTime(),
            'revoked_at' => $this->dateTime(),
            'created_at' => $this->dateTime()->notNull(),
        ], self::OPTS);
        $this->createIndex('api_key_hash', 'api_key', 'key_hash', true);
        $this->createIndex('api_key_user', 'api_key', ['user_id', 'revoked_at']);
        $this->addForeignKey('api_key_user_fk', 'api_key', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('api_key');
    }
}
