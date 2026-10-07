<?php

use yii\db\Migration;

/**
 * Core directory tables (MySQL 8 / MariaDB 10.6+). Port of the old Postgres db/schema.sql.
 * All DATETIME values are stored in UTC.
 */
class m260101_000001_create_directory_tables extends Migration
{
    private const OPTS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function safeUp()
    {
        $this->createTable('sources', [
            'state' => 'CHAR(2) NOT NULL',
            'state_name' => $this->string(64)->notNull(),
            'method' => "ENUM('api','file','scrape','tbd') NOT NULL DEFAULT 'tbd'",
            'url' => $this->string(500),
            'schedule' => $this->string(100),
            'terms_notes' => $this->text(),
            'last_success_at' => $this->dateTime(),
            'PRIMARY KEY (state)',
        ], self::OPTS);

        $this->createTable('registered_agents', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'name' => $this->string(255)->notNull(),
            'address' => $this->string(500),
            'is_commercial_agent' => $this->boolean()->notNull()->defaultValue(false),
        ], self::OPTS);
        $this->createIndex('registered_agents_name', 'registered_agents', 'name');
        $this->execute('CREATE FULLTEXT INDEX registered_agents_name_ft ON registered_agents (name)');

        $this->createTable('entities', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'state' => 'CHAR(2) NOT NULL',
            'state_filing_number' => $this->string(64)->notNull(),
            'url_key' => $this->string(64)->notNull(),          // lowercase alnum filing number, used in URLs
            'slug' => $this->string(160)->notNull(),            // slugified name (cosmetic, for URLs)
            'name' => $this->string(255)->notNull(),
            'entity_type' => $this->string(64)->notNull(),      // LLC, Corporation, Foreign LLC, ...
            'status' => $this->string(64)->notNull(),           // Active, Inactive, Dissolved, ...
            'formation_date' => $this->date(),
            'filed_at' => $this->dateTime()->notNull(),         // timestamp on the state record (UTC)
            'indexed_at' => $this->dateTime()->notNull(),       // when we made it searchable (UTC)
            'principal_address' => $this->string(500),
            'mailing_address' => $this->string(500),
            'county' => $this->string(100),
            'naics_guess' => $this->string(10),
            'naics_label' => $this->string(150),
            'registered_agent_id' => $this->bigInteger()->unsigned(),
            'source_url' => $this->string(500),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], self::OPTS);
        $this->createIndex('entities_state_filing', 'entities', ['state', 'state_filing_number'], true);
        $this->createIndex('entities_state_urlkey', 'entities', ['state', 'url_key'], true);
        $this->createIndex('entities_state_filed', 'entities', ['state', 'filed_at']);
        $this->createIndex('entities_filed', 'entities', 'filed_at');
        $this->createIndex('entities_indexed', 'entities', 'indexed_at');
        $this->createIndex('entities_type_status', 'entities', ['entity_type', 'status']);
        $this->createIndex('entities_state_county', 'entities', ['state', 'county']);
        $this->execute('CREATE FULLTEXT INDEX entities_name_ft ON entities (name)');
        $this->addForeignKey('entities_source_fk', 'entities', 'state', 'sources', 'state');
        $this->addForeignKey('entities_agent_fk', 'entities', 'registered_agent_id', 'registered_agents', 'id', 'SET NULL');

        // History: one row per observed version of an entity record.
        $this->createTable('entity_versions', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'entity_id' => $this->bigInteger()->unsigned()->notNull(),
            'observed_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'raw_s3_key' => $this->string(500),
            'data' => $this->json()->notNull(),
        ], self::OPTS);
        $this->createIndex('entity_versions_entity', 'entity_versions', ['entity_id', 'observed_at']);
        $this->addForeignKey('entity_versions_entity_fk', 'entity_versions', 'entity_id', 'entities', 'id', 'CASCADE');

        $this->createTable('people', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'entity_id' => $this->bigInteger()->unsigned()->notNull(),
            'name' => $this->string(255)->notNull(),
            'role' => "ENUM('officer','manager','member','organizer','director','agent') NOT NULL",
            'title' => $this->string(150),
            'address' => $this->string(500),
        ], self::OPTS);
        $this->createIndex('people_entity', 'people', 'entity_id');
        $this->execute('CREATE FULLTEXT INDEX people_name_ft ON people (name)');
        $this->addForeignKey('people_entity_fk', 'people', 'entity_id', 'entities', 'id', 'CASCADE');

        $this->createTable('filings', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'entity_id' => $this->bigInteger()->unsigned()->notNull(),
            'filing_type' => $this->string(64)->notNull(),      // formation, amendment, annual_report, ...
            'filing_date' => $this->date()->notNull(),
            'document_url' => $this->string(500),
        ], self::OPTS);
        $this->createIndex('filings_entity', 'filings', ['entity_id', 'filing_date']);
        $this->addForeignKey('filings_entity_fk', 'filings', 'entity_id', 'entities', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('filings');
        $this->dropTable('people');
        $this->dropTable('entity_versions');
        $this->dropTable('entities');
        $this->dropTable('registered_agents');
        $this->dropTable('sources');
    }
}
