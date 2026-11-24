<?php

use yii\db\Migration;

class m261127_000001_create_saved_search extends Migration
{
    public function safeUp()
    {
        $this->createTable('saved_search', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->bigInteger()->unsigned()->notNull(),
            'name' => $this->string(100)->notNull(),
            'params' => $this->string(1000)->notNull(),
            'params_hash' => 'CHAR(64) NOT NULL',
            'email_enabled' => $this->boolean()->notNull()->defaultValue(1),
            'unsubscribe_token' => 'CHAR(32) NOT NULL',
            'cursor_at' => $this->dateTime()->notNull(),
            'last_run_on' => $this->date(),
            'last_sent_at' => $this->dateTime(),
            'last_match_count' => $this->integer()->unsigned(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->createIndex('saved_search_user_params', 'saved_search', ['user_id', 'params_hash'], true);
        $this->createIndex('saved_search_token', 'saved_search', 'unsubscribe_token', true);
        $this->createIndex('saved_search_email', 'saved_search', ['email_enabled', 'last_run_on']);
        $this->addForeignKey('saved_search_user_fk', 'saved_search', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('saved_search');
    }
}
