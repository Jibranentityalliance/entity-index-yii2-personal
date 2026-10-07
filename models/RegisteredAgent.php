<?php

namespace app\models;

use yii\db\ActiveRecord;

/** @property int $id @property string $name @property string|null $address @property int $is_commercial_agent */
class RegisteredAgent extends ActiveRecord
{
    public static function tableName()
    {
        return 'registered_agents';
    }
}
