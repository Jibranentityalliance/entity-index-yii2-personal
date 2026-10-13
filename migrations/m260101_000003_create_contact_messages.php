<?php

use yii\db\Migration;

/** Messages sent through the public /contact form. */
class m260101_000003_create_contact_messages extends Migration
{
    public function safeUp()
    {
        $this->createTable('contact_messages', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'name' => $this->string(150)->notNull(),
            'email' => $this->string(254)->notNull(),
            'topic' => $this->string(50)->notNull()->defaultValue('general'),
            'message' => $this->text()->notNull(),
            'status' => "ENUM('new','done') NOT NULL DEFAULT 'new'",
            'created_at' => $this->dateTime()->notNull(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->createIndex('contact_messages_status', 'contact_messages', ['status', 'created_at']);
    }

    public function safeDown()
    {
        $this->dropTable('contact_messages');
    }
}
