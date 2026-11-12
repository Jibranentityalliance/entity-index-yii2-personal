<?php

use yii\db\Migration;

class m261112_000001_create_company_lookup extends Migration
{
    private const OPTS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function safeUp()
    {
        $this->createTable('company_lookup', [
            'user_id' => $this->bigInteger()->unsigned()->notNull(),
            'day' => $this->date()->notNull(),
            'entity_id' => $this->bigInteger()->unsigned()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY (user_id, day, entity_id)',
        ], self::OPTS);
        $this->addForeignKey('company_lookup_user_fk', 'company_lookup', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('company_lookup');
    }
}
