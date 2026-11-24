<?php

namespace app\models;

use yii\db\ActiveRecord;

class WebhookEvent extends ActiveRecord
{
    public static function tableName()
    {
        return 'webhook_event';
    }
}
