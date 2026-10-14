<?php

use yii\db\Migration;

/**
 * Local copy of `manual_companies` from the EIN skip-tracing database, with its `data` JSON normalized.
 * Filled by `php yii ein-sync/companies` (app\services\EinSkipTracing\ManualCompanySync). All DATETIME values are UTC.
 *
 * One `ein_companies` row per source row: the source has repeat submissions of the same company, so rows are not merged.
 */
class m261014_000001_create_ein_skip_tracing_tables extends Migration
{
    private const OPTS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function safeUp()
    {
        $this->createTable('ein_companies', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'source_id' => $this->integer()->unsigned()->notNull(),     // manual_companies.id
            'source_user_id' => $this->integer(),                       // manual_companies.user_id
            'state' => 'CHAR(2) NULL',
            'document_number' => $this->string(64),
            'name' => $this->string(255),
            'entity_type' => $this->string(150),
            'is_active' => $this->boolean(),                            // company.status "1"/"0"
            'date_filed' => $this->date(),                              // parsed; NULL when the raw value is not a date
            'date_filed_raw' => $this->string(64),
            'effective_date' => $this->date(),
            'effective_date_raw' => $this->string(64),
            'company_url' => $this->string(2048),
            'some_info_found' => $this->boolean(),
            'no_info_found' => $this->boolean(),
            'company_data_found' => $this->boolean(),                   // more_info[0].company_data_found
            'no_officer_reason' => $this->string(64),                   // more_info[0].no_officer_reason
            // Source columns that change after insert; a snapshot from the last sync of this row.
            'qualified' => $this->integer(),
            'processed' => $this->integer(),
            'ignored_validation_count' => $this->integer(),
            'source_date_added' => $this->dateTime(),
            'source_hash' => 'CHAR(64) NOT NULL',                       // sha256 of the synced source values; unchanged rows are skipped
            'synced_at' => $this->dateTime()->notNull(),
        ], self::OPTS);
        $this->createIndex('ein_companies_source', 'ein_companies', 'source_id', true);
        $this->createIndex('ein_companies_state_doc', 'ein_companies', ['state', 'document_number']);
        $this->createIndex('ein_companies_date_filed', 'ein_companies', 'date_filed');
        $this->createIndex('ein_companies_date_added', 'ein_companies', 'source_date_added');
        $this->createIndex('ein_companies_qualified', 'ein_companies', 'qualified');

        // registered_agent and officers[] share one shape, so they share one table.
        $this->createTable('ein_company_people', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'company_id' => $this->bigInteger()->unsigned()->notNull(),
            'role' => "ENUM('registered_agent','officer') NOT NULL",
            'position' => $this->smallInteger()->unsigned()->notNull(), // order within officers[]; 0 for the agent
            'first_name' => $this->string(150),
            'last_name' => $this->string(150),
            'full_name' => $this->string(255),
            'title' => $this->string(100),                              // o_title
            'is_ra' => $this->boolean(),                                // the `ra` flag
            'is_agent_company' => $this->boolean(),
            'some_info_found' => $this->boolean(),
            'no_info_found' => $this->boolean(),
            'phone' => $this->string(32),
            'full_address' => $this->string(500),                       // as entered; address/city/state/zip are the parsed parts
            'address' => $this->string(255),
            'city' => $this->string(100),
            'state' => $this->string(32),
            'zip_code' => $this->string(10),
            'mailing_full_address' => $this->string(500),
            'mailing_address' => $this->string(255),
            'mailing_city' => $this->string(100),
            'mailing_state' => $this->string(32),
            'mailing_zip_code' => $this->string(10),
            'address_source' => $this->string(32),                      // who parsed the address: Smarty, Google, ...
        ], self::OPTS);
        $this->createIndex('ein_company_people_company', 'ein_company_people', ['company_id', 'role', 'position']);
        $this->createIndex('ein_company_people_name', 'ein_company_people', ['last_name', 'first_name']);
        $this->createIndex('ein_company_people_state_zip', 'ein_company_people', ['state', 'zip_code']);
        $this->addForeignKey('ein_company_people_company_fk', 'ein_company_people', 'company_id', 'ein_companies', 'id', 'CASCADE');

        // Cursor per sync job: the highest source id that has been synced.
        $this->createTable('ein_sync_state', [
            'name' => $this->string(64)->notNull(),
            'last_source_id' => $this->integer()->unsigned()->notNull(),
            'last_run_at' => $this->dateTime(),
            'last_error' => $this->text(),
            'PRIMARY KEY (name)',
        ], self::OPTS);
    }

    public function safeDown()
    {
        $this->dropTable('ein_sync_state');
        $this->dropTable('ein_company_people');
        $this->dropTable('ein_companies');
    }
}
