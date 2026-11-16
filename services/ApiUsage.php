<?php

namespace app\services;

use app\models\User;
use Yii;
use yii\db\Query;

class ApiUsage
{
    public static function limitsFor(User $user): ?array
    {
        return $user->isAdmin() ? null : $user->withOverrides('api', Yii::$app->params['apiLimits'][$user->plan] ?? null);
    }

    public static function consume(User $user): array
    {
        $now = time();
        $limits = self::limitsFor($user);
        $minute = self::bump($user->id, 'n:' . gmdate('Y-m-d\TH:i', $now));
        $minuteResetAt = $now - $now % 60 + 60;
        $monthResetAt = self::monthResetAt($now);
        $result = [
            'ok' => true,
            'scope' => null,
            'retryAfter' => 0,
            'minute' => ['limit' => $limits['perMinute'] ?? null, 'used' => $minute, 'resetsAt' => $minuteResetAt],
            'month' => ['limit' => $limits['monthlyCalls'] ?? null, 'used' => 0, 'resetsAt' => $monthResetAt],
            'rows' => ['limit' => $limits['monthlyRows'] ?? null, 'used' => self::rowsUsed($user->id, $now), 'resetsAt' => $monthResetAt],
        ];
        if ($limits !== null && $minute > $limits['perMinute']) {
            return ['ok' => false, 'scope' => 'minute', 'retryAfter' => $minuteResetAt - $now] + $result;
        }
        $result['month']['used'] = self::bump($user->id, 'm:' . gmdate('Y-m', $now));
        if ($limits !== null && $result['month']['used'] > $limits['monthlyCalls']) {
            return ['ok' => false, 'scope' => 'month', 'retryAfter' => $monthResetAt - $now] + $result;
        }
        if ($limits !== null && $result['rows']['used'] >= $limits['monthlyRows']) {
            return ['ok' => false, 'scope' => 'rows', 'retryAfter' => $monthResetAt - $now] + $result;
        }
        if (random_int(1, 200) === 1) {
            self::prune($now);
        }
        return $result;
    }

    public static function rowsRemaining(User $user): ?int
    {
        $limits = self::limitsFor($user);
        return $limits === null ? null : max(0, $limits['monthlyRows'] - self::rowsUsed($user->id, time()));
    }

    public static function addRows(User $user, int $rows): array
    {
        $now = time();
        $limits = self::limitsFor($user);
        $used = $rows > 0 ? self::bump($user->id, 'r:' . gmdate('Y-m', $now), $rows) : self::rowsUsed($user->id, $now);
        return ['limit' => $limits['monthlyRows'] ?? null, 'used' => $used, 'resetsAt' => self::monthResetAt($now)];
    }

    public static function monthSummary(User $user): array
    {
        $now = time();
        $limits = self::limitsFor($user);
        $calls = (int) (new Query())->select('calls')->from('api_usage')
            ->where(['user_id' => $user->id, 'bucket' => 'm:' . gmdate('Y-m', $now)])->scalar();
        return [
            'limit' => $limits['monthlyCalls'] ?? null,
            'used' => $calls,
            'rowLimit' => $limits['monthlyRows'] ?? null,
            'rowsUsed' => self::rowsUsed($user->id, $now),
            'resetsAt' => self::monthResetAt($now),
        ];
    }

    private static function rowsUsed(int $userId, int $now): int
    {
        return (int) (new Query())->select('calls')->from('api_usage')
            ->where(['user_id' => $userId, 'bucket' => 'r:' . gmdate('Y-m', $now)])->scalar();
    }

    public static function bump(int $userId, string $bucket, int $by = 1): int
    {
        $db = Yii::$app->db;
        $db->createCommand(
            'INSERT INTO api_usage (user_id, bucket, calls) VALUES (:u, :b, :n) ON DUPLICATE KEY UPDATE calls = calls + :n',
            [':u' => $userId, ':b' => $bucket, ':n' => $by]
        )->execute();
        return (int) $db->createCommand('SELECT calls FROM api_usage WHERE user_id = :u AND bucket = :b', [':u' => $userId, ':b' => $bucket])->queryScalar();
    }

    private static function prune(int $now): void
    {
        Yii::$app->db->createCommand()->delete('api_usage', ['and', ['like', 'bucket', 'n:%', false], ['<', 'bucket', 'n:' . gmdate('Y-m-d\TH:i', $now - 7200)]])->execute();
    }

    public static function monthResetAt(int $now): int
    {
        return (new \DateTimeImmutable('@' . $now))->modify('first day of next month')->setTime(0, 0)->getTimestamp();
    }
}
