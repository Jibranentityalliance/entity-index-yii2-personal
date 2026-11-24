<?php

use yii\db\Migration;

class m261125_000001_create_webhooks extends Migration
{
    private const OPTS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function safeUp()
    {
        $this->createTable('webhook_endpoint', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->bigInteger()->unsigned()->notNull(),
            'url' => $this->string(2048)->notNull(),
            'description' => $this->string(100),
            'secret' => $this->string(80)->notNull(),
            'states' => $this->string(255),
            'types' => $this->string(500),
            'status' => "ENUM('active','paused','disabled') NOT NULL DEFAULT 'active'",
            'disabled_reason' => $this->string(255),
            'cursor_event_id' => $this->bigInteger()->unsigned()->notNull()->defaultValue(0),
            'failure_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'last_success_at' => $this->dateTime(),
            'last_failure_at' => $this->dateTime(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], self::OPTS);
        $this->createIndex('webhook_endpoint_user', 'webhook_endpoint', ['user_id', 'status']);
        $this->createIndex('webhook_endpoint_status', 'webhook_endpoint', 'status');
        $this->addForeignKey('webhook_endpoint_user_fk', 'webhook_endpoint', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('webhook_event', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'entity_id' => $this->bigInteger()->unsigned()->notNull(),
            'published_at' => $this->dateTime()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ], self::OPTS);
        $this->createIndex('webhook_event_entity', 'webhook_event', 'entity_id', true);
        $this->createIndex('webhook_event_created', 'webhook_event', 'created_at');

        $this->createTable('webhook_delivery', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'endpoint_id' => $this->bigInteger()->unsigned()->notNull(),
            'event' => $this->string(40)->notNull(),
            'entity_ids' => $this->text()->notNull(),
            'entity_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'status' => "ENUM('pending','success','failed') NOT NULL DEFAULT 'pending'",
            'attempts' => $this->smallInteger()->unsigned()->notNull()->defaultValue(0),
            'next_attempt_at' => $this->dateTime(),
            'last_attempt_at' => $this->dateTime(),
            'response_code' => $this->smallInteger()->unsigned(),
            'response_body' => $this->string(500),
            'error' => $this->string(255),
            'created_at' => $this->dateTime()->notNull(),
            'delivered_at' => $this->dateTime(),
        ], self::OPTS);
        $this->createIndex('webhook_delivery_due', 'webhook_delivery', ['status', 'next_attempt_at']);
        $this->createIndex('webhook_delivery_endpoint', 'webhook_delivery', ['endpoint_id', 'id']);
        $this->addForeignKey('webhook_delivery_endpoint_fk', 'webhook_delivery', 'endpoint_id', 'webhook_endpoint', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('webhook_delivery');
        $this->dropTable('webhook_event');
        $this->dropTable('webhook_endpoint');
    }
}
