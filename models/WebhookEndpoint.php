<?php

namespace app\models;

use app\helpers\States;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class WebhookEndpoint extends ActiveRecord
{
    public const SECRET_PREFIX = 'whsec_';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_DISABLED = 'disabled';

    public $stateList = [];
    public $typeList = [];

    public static function tableName()
    {
        return 'webhook_endpoint';
    }

    public static function forUser(int $userId): ActiveQuery
    {
        return static::find()->where(['user_id' => $userId])->orderBy(['id' => SORT_ASC]);
    }

    public static function newSecret(): string
    {
        return self::SECRET_PREFIX . bin2hex(random_bytes(24));
    }

    public function rules()
    {
        return [
            [['url', 'description'], 'trim'],
            ['url', 'required'],
            ['url', 'string', 'max' => 2048],
            ['url', 'url', 'validSchemes' => ['https', 'http'], 'enableIDN' => false],
            ['url', 'validateUrl'],
            ['description', 'string', 'max' => 100],
            ['stateList', 'each', 'rule' => ['in', 'range' => array_keys(States::all())]],
            ['typeList', 'each', 'rule' => ['in', 'range' => States::ENTITY_TYPES]],
        ];
    }

    public function attributeLabels()
    {
        return ['url' => 'Endpoint URL', 'stateList' => 'States', 'typeList' => 'Entity types'];
    }

    public function validateUrl($attribute)
    {
        if ($this->hasErrors($attribute)) {
            return;
        }
        $parts = parse_url((string) $this->url);
        $allowPrivate = (bool) (Yii::$app->params['webhooks']['allowPrivate'] ?? false);
        if (($parts['scheme'] ?? '') !== 'https' && !$allowPrivate) {
            $this->addError($attribute, 'Use an https:// URL.');
        } elseif (!$allowPrivate && self::isPrivateHost((string) ($parts['host'] ?? ''))) {
            $this->addError($attribute, 'The URL must point to a public server.');
        }
    }

    public static function isPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !self::isPublicIp($host);
        }
        return false;
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->stateList = $this->states ? explode(',', $this->states) : [];
        $this->typeList = $this->types ? (json_decode($this->types, true) ?: []) : [];
    }

    public function beforeSave($insert)
    {
        $now = gmdate('Y-m-d H:i:s');
        $states = array_values(array_unique(array_filter((array) $this->stateList)));
        $types = array_values(array_unique(array_filter((array) $this->typeList)));
        sort($states);
        $this->states = $states ? implode(',', $states) : null;
        $this->types = $types ? json_encode($types) : null;
        if ($insert) {
            $this->created_at = $now;
            $this->secret = $this->secret ?: self::newSecret();
            $this->status = $this->status ?: self::STATUS_ACTIVE;
            $this->cursor_event_id = (int) (WebhookEvent::find()->max('id') ?? 0);
        }
        $this->updated_at = $now;
        return parent::beforeSave($insert);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function filterSummary(): string
    {
        $states = $this->stateList ? implode(', ', $this->stateList) : 'All states';
        $types = $this->typeList ? implode(', ', $this->typeList) : 'all entity types';
        return "$states; $types";
    }

    public function getDeliveries(): ActiveQuery
    {
        return $this->hasMany(WebhookDelivery::class, ['endpoint_id' => 'id']);
    }
}
