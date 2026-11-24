<?php

use yii\db\Migration;

class m261126_000001_add_bulk_file_kind extends Migration
{
    public function safeUp()
    {
        $this->addColumn('bulk_file', 'kind', "ENUM('full','daily','weekly') NOT NULL DEFAULT 'full' AFTER id");
        $this->dropIndex('bulk_file_date_scope', 'bulk_file');
        $this->createIndex('bulk_file_kind_date_scope', 'bulk_file', ['kind', 'build_date', 'scope'], true);
    }

    public function safeDown()
    {
        $this->delete('bulk_file', ['!=', 'kind', 'full']);
        $this->dropIndex('bulk_file_kind_date_scope', 'bulk_file');
        $this->createIndex('bulk_file_date_scope', 'bulk_file', ['build_date', 'scope'], true);
        $this->dropColumn('bulk_file', 'kind');
    }
}
