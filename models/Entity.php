<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * A business filing. DATETIME columns hold UTC.
 *
 * @property int $id
 * @property string $state
 * @property string $state_filing_number
 * @property string $url_key
 * @property string $slug
 * @property string $name
 * @property string $entity_type
 * @property string $status
 * @property string|null $formation_date
 * @property string $filed_at
 * @property string $indexed_at
 * @property string|null $principal_address
 * @property string|null $mailing_address
 * @property string|null $county
 * @property string|null $naics_guess
 * @property string|null $naics_label
 * @property int|null $registered_agent_id
 * @property string|null $source_url
 *
 * @property RegisteredAgent|null $agent
 * @property Person[] $people
 * @property Filing[] $filings
 */
class Entity extends ActiveRecord
{
    public static function tableName()
    {
        return 'entities';
    }

    public function getAgent(): ActiveQuery
    {
        return $this->hasOne(RegisteredAgent::class, ['id' => 'registered_agent_id']);
    }

    public function getPeople(): ActiveQuery
    {
        return $this->hasMany(Person::class, ['entity_id' => 'id'])->orderBy(['id' => SORT_ASC]);
    }

    public function getFilings(): ActiveQuery
    {
        return $this->hasMany(Filing::class, ['entity_id' => 'id'])->orderBy(['filing_date' => SORT_DESC, 'id' => SORT_DESC]);
    }
}
