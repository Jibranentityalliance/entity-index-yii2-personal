<?php

use yii\db\Migration;

/**
 * Lets `entities` / `people` hold companies converted from the replicated portal table `manual_companies`
 * (app\services\MirrorConverter):
 *
 * - entities.source_id: the newest manual_companies row behind the company. Repeat submissions of the same
 *   state + filing number update one company.
 * - Malformed source rows are kept: missing values are stored empty/NULL and shown as "N/A". A row without a valid
 *   state is stored with state 'NA' (no FK to sources any more), a row without a filing number gets url_key r{source_id}.
 * - filed_at may be NULL (unknown filing date).
 * - people.phone: contact phone, shown to paid plans only (masked until clicked).
 * - mirror_map: which source rows feed which company (to re-pick the newest one when a row is deleted).
 * - mirror_changes: filled by triggers on the replica (docs/mirror-triggers.sql); drained by `php yii mirror/apply`.
 * - mirror_state: progress of `php yii mirror/backfill`.
 */
class m261021_000001_mirror_entities extends Migration
{
    private const OPTS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function safeUp()
    {
        $this->dropForeignKey('entities_source_fk', 'entities');
        $this->dropIndex('entities_state_filing', 'entities');
        $this->createIndex('entities_state_filing', 'entities', ['state', 'state_filing_number']);
        $this->alterColumn('entities', 'filed_at', $this->dateTime()->null());
        $this->addColumn('entities', 'source_id', $this->integer()->unsigned()->null()->after('id'));
        $this->createIndex('entities_source', 'entities', 'source_id');

        $this->addColumn('people', 'phone', $this->string(32)->null()->after('address'));

        $this->createTable('mirror_map', [
            'source_id' => $this->integer()->unsigned()->notNull(),
            'entity_id' => $this->bigInteger()->unsigned()->notNull(),
            'PRIMARY KEY (source_id)',
        ], self::OPTS);
        $this->createIndex('mirror_map_entity', 'mirror_map', ['entity_id', 'source_id']);

        $this->createTable('mirror_changes', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'source_id' => $this->integer()->unsigned()->notNull(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], self::OPTS);

        $this->createTable('mirror_state', [
            'name' => $this->string(64)->notNull(),
            'value' => $this->bigInteger()->notNull()->defaultValue(0),
            'updated_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY (name)',
        ], self::OPTS);
    }

    public function safeDown()
    {
        $this->dropTable('mirror_state');
        $this->dropTable('mirror_changes');
        $this->dropTable('mirror_map');
        $this->dropColumn('people', 'phone');
        $this->dropIndex('entities_source', 'entities');
        $this->dropColumn('entities', 'source_id');
        $this->alterColumn('entities', 'filed_at', $this->dateTime()->notNull());
        $this->dropIndex('entities_state_filing', 'entities');
        $this->createIndex('entities_state_filing', 'entities', ['state', 'state_filing_number'], true);
        $this->addForeignKey('entities_source_fk', 'entities', 'state', 'sources', 'state');
    }
}
