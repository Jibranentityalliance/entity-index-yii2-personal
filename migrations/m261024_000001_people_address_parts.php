<?php

use yii\db\Migration;

/**
 * The parsed parts of a person's address (street, city, state, ZIP), next to the one-line `address`.
 * Filled by app\services\MirrorConverter; rows converted before this migration get them on the next
 * `php yii mirror/backfill --restart`.
 */
class m261024_000001_people_address_parts extends Migration
{
    public function safeUp()
    {
        $this->addColumn('people', 'street', $this->string(255)->null()->after('address'));
        $this->addColumn('people', 'city', $this->string(100)->null()->after('street'));
        $this->addColumn('people', 'state', $this->string(32)->null()->after('city'));
        $this->addColumn('people', 'zip', $this->string(10)->null()->after('state'));
    }

    public function safeDown()
    {
        $this->dropColumn('people', 'zip');
        $this->dropColumn('people', 'state');
        $this->dropColumn('people', 'city');
        $this->dropColumn('people', 'street');
    }
}
