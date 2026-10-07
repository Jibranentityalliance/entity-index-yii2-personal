<?php

use yii\db\Migration;

/**
 * Portal accounts and the two public forms (waitlist, opt-out requests).
 */
class m260101_000002_create_app_tables extends Migration
{
    private const OPTS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function safeUp()
    {
        $this->createTable('user', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'email' => $this->string(254)->notNull(),           // case-insensitive via collation
            'name' => $this->string(150),
            'password_hash' => $this->string(255)->notNull(),
            'auth_key' => $this->string(32)->notNull(),
            'role' => "ENUM('customer','admin') NOT NULL DEFAULT 'customer'",
            'plan' => "ENUM('free','pro','business','bulk') NOT NULL DEFAULT 'free'",
            'status' => $this->smallInteger()->notNull()->defaultValue(10), // 10 active, 0 disabled
            'last_login_at' => $this->dateTime(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], self::OPTS);
        $this->createIndex('user_email', 'user', 'email', true);

        $this->createTable('waitlist', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'email' => $this->string(254)->notNull(),
            'use_case' => $this->string(500),
            'created_at' => $this->dateTime()->notNull(),
        ], self::OPTS);
        $this->createIndex('waitlist_email', 'waitlist', 'email', true);

        $this->createTable('opt_out_requests', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'entity_ref' => $this->string(500)->notNull(),      // page URL or state + filing number
            'person_name' => $this->string(255)->notNull(),
            'email' => $this->string(254)->notNull(),
            'reason' => $this->text(),
            'status' => "ENUM('pending','done','rejected') NOT NULL DEFAULT 'pending'",
            'created_at' => $this->dateTime()->notNull(),
            'resolved_at' => $this->dateTime(),
        ], self::OPTS);
        $this->createIndex('opt_out_status', 'opt_out_requests', ['status', 'created_at']);
    }

    public function safeDown()
    {
        $this->dropTable('opt_out_requests');
        $this->dropTable('waitlist');
        $this->dropTable('user');
    }
}
