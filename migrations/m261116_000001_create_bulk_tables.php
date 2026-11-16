<?php

use yii\db\Migration;

class m261116_000001_create_bulk_tables extends Migration
{
    private const OPTS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function safeUp()
    {
        $this->createTable('bulk_file', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'build_date' => $this->date()->notNull(),
            'scope' => $this->string(3)->notNull(),
            'file_key' => $this->string(190)->notNull(),
            'file_name' => $this->string(190)->notNull(),
            'row_count' => $this->bigInteger()->unsigned()->notNull(),
            'bytes' => $this->bigInteger()->unsigned()->notNull(),
            'sha256' => 'CHAR(64) NOT NULL',
            'created_at' => $this->dateTime()->notNull(),
        ], self::OPTS);
        $this->createIndex('bulk_file_date_scope', 'bulk_file', ['build_date', 'scope'], true);

        $this->createTable('bulk_download', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->bigInteger()->unsigned()->notNull(),
            'file_id' => $this->bigInteger()->unsigned()->null(),
            'file_name' => $this->string(190)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ], self::OPTS);
        $this->createIndex('bulk_download_user_day', 'bulk_download', ['user_id', 'created_at']);
        $this->addForeignKey('bulk_download_user_fk', 'bulk_download', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('bulk_download_file_fk', 'bulk_download', 'file_id', 'bulk_file', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('bulk_download');
        $this->dropTable('bulk_file');
    }
}
