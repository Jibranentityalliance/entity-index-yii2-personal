<?php

namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ApiKey extends ActiveRecord
{
    public const TOKEN_PREFIX = 'eik_';
    public const MAX_ACTIVE_PER_USER = 5;
    private const TOUCH_INTERVAL = 300;

    public static function tableName()
    {
        return 'api_key';
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function issue(User $user, string $name): array
    {
        $token = self::TOKEN_PREFIX . bin2hex(random_bytes(24));
        $key = new self([
            'user_id' => $user->id,
            'name' => mb_substr(trim($name), 0, 100),
            'key_prefix' => substr($token, 0, 12),
            'key_hash' => self::hashToken($token),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $key->save(false);
        return [$key, $token];
    }

    public static function findActiveByToken(string $token): ?self
    {
        if (strncmp($token, self::TOKEN_PREFIX, strlen(self::TOKEN_PREFIX)) !== 0) {
            return null;
        }
        return static::find()
            ->where(['key_hash' => self::hashToken($token)])
            ->andWhere(['revoked_at' => null])
            ->one();
    }

    public static function activeForUser(int $userId): ActiveQuery
    {
        return static::find()
            ->where(['user_id' => $userId, 'revoked_at' => null])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC]);
    }

    public static function activeCount(int $userId): int
    {
        return (int) static::activeForUser($userId)->count();
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function revoke(): void
    {
        if ($this->isActive()) {
            $this->updateAttributes(['revoked_at' => gmdate('Y-m-d H:i:s')]);
        }
    }

    public function touchUsed(): void
    {
        $last = $this->last_used_at ? strtotime($this->last_used_at . ' UTC') : 0;
        if (time() - $last >= self::TOUCH_INTERVAL) {
            $this->updateAttributes(['last_used_at' => gmdate('Y-m-d H:i:s')]);
        }
    }
}
