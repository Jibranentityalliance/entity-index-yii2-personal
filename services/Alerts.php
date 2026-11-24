<?php

namespace app\services;

use app\models\SavedSearch;
use app\models\User;
use Yii;

class Alerts
{
    public static function cfg(string $key): mixed
    {
        $c = (Yii::$app->params['alerts'] ?? []) + ['maxSearches' => 10, 'emailRows' => 25];
        return $c[$key];
    }

    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone((string) (Yii::$app->params['reportTz'] ?? 'UTC'))))->format('Y-m-d');
    }

    public static function matches(SavedSearch $search, string $after, string $until, int $limit): array
    {
        $q = EntityRepository::filteredQuery($search->filters())
            ->andWhere(['>', 'e.publish_at', $after])
            ->andWhere(['<=', 'e.publish_at', $until]);
        $total = (int) (clone $q)->select('COUNT(*)')->orderBy(null)->scalar();
        $items = $total === 0 ? [] : $q->select(['e.id', 'e.state', 'e.url_key', 'e.slug', 'e.name', 'e.entity_type', 'e.status', 'e.filed_at', 'e.county'])
            ->orderBy(['e.filed_at' => SORT_DESC, 'e.id' => SORT_DESC])->limit($limit)->all();
        return ['total' => $total, 'items' => $items];
    }

    public static function run(bool $force = false): array
    {
        $today = self::today();
        $now = gmdate('Y-m-d H:i:s');
        $limit = (int) self::cfg('emailRows');
        $stats = ['checked' => 0, 'sent' => 0, 'companies' => 0, 'skipped' => 0, 'failed' => 0];
        $query = SavedSearch::find()->with('user')->where(['email_enabled' => 1]);
        if (!$force) {
            $query->andWhere(['or', ['last_run_on' => null], ['<', 'last_run_on', $today]]);
        }
        foreach ($query->orderBy(['id' => SORT_ASC])->each(100) as $search) {
            $user = $search->user;
            $after = $search->cursor_at;
            $search->cursor_at = $now;
            $search->last_run_on = $today;
            if ($user === null || (int) $user->status !== User::STATUS_ACTIVE || !$user->hasFullAccess()) {
                $search->save(false, ['cursor_at', 'last_run_on', 'updated_at']);
                $stats['skipped']++;
                continue;
            }
            $stats['checked']++;
            $m = self::matches($search, $after, $now, $limit);
            if ($m['total'] > 0) {
                if (Mail::savedSearchAlert($user, $search, $m['total'], $m['items'])) {
                    $stats['sent']++;
                    $stats['companies'] += $m['total'];
                    $search->last_sent_at = $now;
                } else {
                    $stats['failed']++;
                    $search->cursor_at = $after;
                }
            }
            $search->last_match_count = $m['total'];
            $search->save(false, ['cursor_at', 'last_run_on', 'last_sent_at', 'last_match_count', 'updated_at']);
        }
        return $stats;
    }
}
