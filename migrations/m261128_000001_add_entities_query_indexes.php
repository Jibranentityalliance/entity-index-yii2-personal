<?php

use yii\db\Migration;

class m261128_000001_add_entities_query_indexes extends Migration
{
    public function up()
    {
        $this->execute('ALTER TABLE entities'
            . ' ADD INDEX entities_state (state),'
            . ' ADD INDEX entities_state_type (state, entity_type),'
            . ' ADD INDEX entities_state_type_filed (state, entity_type, filed_at),'
            . ' ADD INDEX entities_state_publish (state, publish_at, indexed_at),'
            . ' ADD INDEX entities_url_key (url_key),'
            . ' ADD INDEX entities_state_county_filed (state, county, filed_at),'
            . ' ADD INDEX entities_publish_indexed (publish_at, indexed_at),'
            . ' DROP INDEX entities_state_county,'
            . ' DROP INDEX entities_publish_at,'
            . ' ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down()
    {
        $this->execute('ALTER TABLE entities'
            . ' ADD INDEX entities_state_county (state, county),'
            . ' ADD INDEX entities_publish_at (publish_at),'
            . ' DROP INDEX entities_state,'
            . ' DROP INDEX entities_state_type,'
            . ' DROP INDEX entities_state_type_filed,'
            . ' DROP INDEX entities_state_publish,'
            . ' DROP INDEX entities_url_key,'
            . ' DROP INDEX entities_state_county_filed,'
            . ' DROP INDEX entities_publish_indexed,'
            . ' ALGORITHM=INPLACE, LOCK=NONE');
    }
}
