<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Portal account.
 *
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property string $password_hash
 * @property string $auth_key
 * @property string $role     customer | admin
 * @property string $plan     free | pro | business | bulk
 * @property string|null $limit_overrides JSON: per-account limit numbers that replace the plan's defaults
 * @property int $status      10 active, 0 disabled
 * @property string|null $last_login_at
 * @property string $created_at
 * @property string $updated_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const STATUS_ACTIVE = 10;
    public const STATUS_DISABLED = 0;
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';
    public const RESET_TTL = 3600;

    public static function tableName()
    {
        return 'user';
    }

    public function beforeSave($insert)
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $now;
            $this->auth_key = $this->auth_key ?: Yii::$app->security->generateRandomString(32);
        }
        $this->updated_at = $now;
        return parent::beforeSave($insert);
    }

    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return null;
    }

    public static function findByEmail(string $email): ?self
    {
        return static::findOne(['email' => $email, 'status' => self::STATUS_ACTIVE]);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return hash_equals((string) $this->auth_key, (string) $authKey);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function issuePasswordReset(): string
    {
        $token = Yii::$app->security->generateRandomString(43);
        $this->password_reset_hash = hash('sha256', $token);
        $this->password_reset_expires_at = gmdate('Y-m-d H:i:s', time() + self::RESET_TTL);
        $this->save(false, ['password_reset_hash', 'password_reset_expires_at', 'updated_at']);
        return $token;
    }

    public function passwordResetIssuedWithin(int $seconds): bool
    {
        return $this->password_reset_expires_at !== null
            && strtotime($this->password_reset_expires_at . ' UTC') - self::RESET_TTL > time() - $seconds;
    }

    public static function findByPasswordResetToken(string $token): ?self
    {
        if (strlen($token) < 32 || strlen($token) > 64) {
            return null;
        }
        return static::find()
            ->where(['password_reset_hash' => hash('sha256', $token), 'status' => self::STATUS_ACTIVE])
            ->andWhere(['>', 'password_reset_expires_at', gmdate('Y-m-d H:i:s')])
            ->one();
    }

    public function changePassword(string $password): void
    {
        $this->setPassword($password);
        $this->auth_key = Yii::$app->security->generateRandomString(32);
        $this->password_reset_hash = null;
        $this->password_reset_expires_at = null;
    }

    /** Paid plans and admins see full records (officers, addresses). */
    public function hasFullAccess(): bool
    {
        return $this->isAdmin() || in_array($this->plan, ['pro', 'business', 'bulk'], true);
    }

    public function isFree(): bool
    {
        return !$this->hasFullAccess();
    }

    public function hasApiAccess(): bool
    {
        return $this->isAdmin() || in_array($this->plan, ['business', 'bulk'], true);
    }

    public function hasExportAccess(): bool
    {
        $limits = Yii::$app->params['exportLimits'] ?? [];
        return $this->isAdmin() || isset($limits[$this->plan]);
    }

    public function hasBulkAccess(): bool
    {
        return $this->isAdmin() || $this->plan === 'bulk';
    }

    public function limitOverrides(): array
    {
        $v = json_decode((string) $this->limit_overrides, true);
        return is_array($v) ? $v : [];
    }

    public function withOverrides(string $group, ?array $base): ?array
    {
        if ($base === null) {
            return null;
        }
        return array_intersect_key($this->limitOverrides()[$group] ?? [], $base) + $base;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
