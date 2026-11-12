<?php

namespace app\services;

use app\models\User;
use Yii;
use yii\db\Query;

class ExportUsage
{
    public static function limitsFor(User $user): ?array
    {
        return Yii::$app->params['exportLimits'][$user->isAdmin() ? 'admin' : $user->plan] ?? null;
    }

    public static function used(User $user): int
    {
        return (int) (new Query())->select('calls')->from('api_usage')
            ->where(['user_id' => $user->id, 'bucket' => self::bucket(time())])->scalar();
    }

    public static function remaining(User $user): ?int
    {
        $cap = self::limitsFor($user)['monthlyRows'] ?? null;
        return $cap === null ? null : max(0, $cap - self::used($user));
    }

    public static function allowance(User $user): int
    {
        $limits = self::limitsFor($user);
        $remaining = self::remaining($user);
        $perFile = (int) ($limits['perFile'] ?? 0);
        return $remaining === null ? $perFile : min($perFile, $remaining);
    }

    public static function add(User $user, int $rows): int
    {
        return $rows > 0 ? ApiUsage::bump($user->id, self::bucket(time()), $rows) : self::used($user);
    }

    public static function summary(User $user): array
    {
        $limits = self::limitsFor($user);
        return [
            'limit' => $limits['monthlyRows'] ?? null,
            'perFile' => (int) ($limits['perFile'] ?? 0),
            'used' => self::used($user),
            'allowance' => self::allowance($user),
            'resetsAt' => ApiUsage::monthResetAt(time()),
        ];
    }

    private static function bucket(int $now): string
    {
        return 'x:' . gmdate('Y-m', $now);
    }
}
