<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class WebhookDelivery extends ActiveRecord
{
    public const EVENT_PUBLISHED = 'entities.published';
    public const EVENT_TEST = 'test';

    public static function tableName()
    {
        return 'webhook_delivery';
    }

    public function entityIds(): array
    {
        return array_map('intval', json_decode((string) $this->entity_ids, true) ?: []);
    }

    public function getEndpoint(): ActiveQuery
    {
        return $this->hasOne(WebhookEndpoint::class, ['id' => 'endpoint_id']);
    }
}
