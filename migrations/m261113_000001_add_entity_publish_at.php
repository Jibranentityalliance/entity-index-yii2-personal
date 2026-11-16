<?php

use yii\db\Migration;

class m261113_000001_add_entity_publish_at extends Migration
{
    public function safeUp()
    {
        $this->addColumn('entities', 'publish_at', $this->dateTime()->null()->after('indexed_at'));
        $this->execute('UPDATE entities SET publish_at = indexed_at');
        $this->createIndex('entities_publish_at', 'entities', 'publish_at');
    }

    public function safeDown()
    {
        $this->dropIndex('entities_publish_at', 'entities');
        $this->dropColumn('entities', 'publish_at');
    }
}
