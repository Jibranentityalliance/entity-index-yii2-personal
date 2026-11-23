<?php

use yii\db\Migration;

class m261124_000001_add_user_password_reset extends Migration
{
    public function safeUp()
    {
        $this->addColumn('user', 'password_reset_hash', 'CHAR(64) NULL AFTER auth_key');
        $this->addColumn('user', 'password_reset_expires_at', $this->dateTime()->null()->after('password_reset_hash'));
        $this->createIndex('user_password_reset', 'user', 'password_reset_hash', true);
    }

    public function safeDown()
    {
        $this->dropIndex('user_password_reset', 'user');
        $this->dropColumn('user', 'password_reset_expires_at');
        $this->dropColumn('user', 'password_reset_hash');
    }
}
