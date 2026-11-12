<?php

namespace app\services;

use app\models\User;
use Yii;
use yii\db\Query;

class FreePlan
{
    public static function limitsFor(User $user): ?array
    {
        return $user->isFree() ? (Yii::$app->params['freeLimits'] ?? null) : null;
    }

    public static function historyFloor(User $user): ?string
    {
        $limits = self::limitsFor($user);
        $latest = $limits === null ? null : EntityRepository::latestFilingDay();
        if ($latest === null) {
            return null;
        }
        return (new \DateTimeImmutable($latest, new \DateTimeZone('UTC')))->modify('-' . max(0, (int) $limits['historyDays'] - 1) . ' days')->format('Y-m-d');
    }

    public static function inHistory(User $user, ?string $filedAt): bool
    {
        $floor = self::historyFloor($user);
        return $floor === null || ($filedAt !== null && substr($filedAt, 0, 10) >= $floor);
    }

    public static function lookup(User $user, int $entityId): bool
    {
        $limits = self::limitsFor($user);
        if ($limits === null) {
            return true;
        }
        $key = ['user_id' => $user->id, 'day' => self::today(), 'entity_id' => $entityId];
        if ((new Query())->from('company_lookup')->where($key)->exists()) {
            return true;
        }
        if (self::usedToday($user) >= (int) $limits['lookupsPerDay']) {
            return false;
        }
        Yii::$app->db->createCommand('INSERT IGNORE INTO company_lookup (user_id, day, entity_id, created_at) VALUES (:u, :d, :e, :c)', [
            ':u' => $user->id, ':d' => $key['day'], ':e' => $entityId, ':c' => gmdate('Y-m-d H:i:s'),
        ])->execute();
        if (random_int(1, 100) === 1) {
            Yii::$app->db->createCommand()->delete('company_lookup', ['<', 'day', gmdate('Y-m-d', time() - 3 * 86400)])->execute();
        }
        return true;
    }

    public static function usedToday(User $user): int
    {
        return (int) (new Query())->from('company_lookup')->where(['user_id' => $user->id, 'day' => self::today()])->count();
    }

    public static function summary(User $user): ?array
    {
        $limits = self::limitsFor($user);
        if ($limits === null) {
            return null;
        }
        $used = self::usedToday($user);
        $cap = (int) $limits['lookupsPerDay'];
        return [
            'historyDays' => (int) $limits['historyDays'],
            'floor' => self::historyFloor($user),
            'ceiling' => EntityRepository::latestFilingDay(),
            'lookupsPerDay' => $cap,
            'used' => $used,
            'remaining' => max(0, $cap - $used),
            'resetsAt' => self::tomorrowStart(),
        ];
    }

    private static function tz(): \DateTimeZone
    {
        return new \DateTimeZone(Yii::$app->params['reportTz'] ?? 'UTC');
    }

    private static function today(): string
    {
        return (new \DateTimeImmutable('now', self::tz()))->format('Y-m-d');
    }

    private static function tomorrowStart(): int
    {
        return (new \DateTimeImmutable('tomorrow', self::tz()))->getTimestamp();
    }
}
