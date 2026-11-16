<?php

use yii\db\Migration;

class m261118_000001_add_entity_publish_override extends Migration
{
    public function safeUp()
    {
        $this->addColumn('entities', 'publish_override', "ENUM('hold','release') NULL AFTER publish_at");
        $this->addColumn('entities', 'publish_override_at', $this->dateTime()->null()->after('publish_override'));
        $this->addColumn('entities', 'publish_override_by', $this->bigInteger()->unsigned()->null()->after('publish_override_at'));
        $this->addForeignKey('entities_override_by_fk', 'entities', 'publish_override_by', 'user', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropForeignKey('entities_override_by_fk', 'entities');
        $this->dropColumn('entities', 'publish_override_by');
        $this->dropColumn('entities', 'publish_override_at');
        $this->dropColumn('entities', 'publish_override');
    }
}
