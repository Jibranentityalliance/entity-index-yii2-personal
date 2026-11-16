<?php

use yii\db\Migration;

class m261119_000001_add_people_hidden_and_opt_out_resolution extends Migration
{
    public function safeUp()
    {
        $this->addColumn('entities', 'people_hidden', $this->boolean()->notNull()->defaultValue(0)->after('publish_override_by'));

        $this->addColumn('opt_out_requests', 'entity_id', $this->bigInteger()->unsigned()->null()->after('entity_ref'));
        $this->addColumn('opt_out_requests', 'entity_state', 'CHAR(2) NULL AFTER entity_id');
        $this->addColumn('opt_out_requests', 'entity_url_key', $this->string(64)->null()->after('entity_state'));
        $this->addColumn('opt_out_requests', 'resolved_by', $this->bigInteger()->unsigned()->null()->after('resolved_at'));
        $this->addColumn('opt_out_requests', 'note', $this->string(500)->null()->after('resolved_by'));
        $this->createIndex('opt_out_entity_key', 'opt_out_requests', ['entity_state', 'entity_url_key', 'status']);
        $this->addForeignKey('opt_out_entity_fk', 'opt_out_requests', 'entity_id', 'entities', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('opt_out_resolved_by_fk', 'opt_out_requests', 'resolved_by', 'user', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropForeignKey('opt_out_resolved_by_fk', 'opt_out_requests');
        $this->dropForeignKey('opt_out_entity_fk', 'opt_out_requests');
        $this->dropIndex('opt_out_entity_key', 'opt_out_requests');
        $this->dropColumn('opt_out_requests', 'note');
        $this->dropColumn('opt_out_requests', 'resolved_by');
        $this->dropColumn('opt_out_requests', 'entity_url_key');
        $this->dropColumn('opt_out_requests', 'entity_state');
        $this->dropColumn('opt_out_requests', 'entity_id');
        $this->dropColumn('entities', 'people_hidden');
    }
}
