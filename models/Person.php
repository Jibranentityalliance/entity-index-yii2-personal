<?php

namespace app\models;

use yii\db\ActiveRecord;

/** @property int $entity_id @property string $name @property string $role @property string|null $title @property string|null $address */
class Person extends ActiveRecord
{
    public static function tableName()
    {
        return 'people';
    }
}
