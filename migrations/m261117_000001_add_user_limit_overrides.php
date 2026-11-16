<?php

use yii\db\Migration;

class m261117_000001_add_user_limit_overrides extends Migration
{
    public function safeUp()
    {
        $this->addColumn('user', 'limit_overrides', $this->text()->null()->after('plan'));
    }

    public function safeDown()
    {
        $this->dropColumn('user', 'limit_overrides');
    }
}
