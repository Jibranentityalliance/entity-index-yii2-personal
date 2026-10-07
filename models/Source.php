<?php

namespace app\models;

use yii\db\ActiveRecord;

/** @property string $state @property string $state_name @property string $method */
class Source extends ActiveRecord
{
    public static function tableName()
    {
        return 'sources';
    }
}
