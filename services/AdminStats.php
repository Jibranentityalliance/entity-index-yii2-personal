<?php

namespace app\services;

use yii\db\Connection;
use yii\db\Query;

class AdminStats
{
    public const CACHE_KEY = 'admin.database.summary';
    public const CACHE_TTL = 300;
    public const COOLDOWN = 'cooldown';
    public const HELD = 'held';
    public const PUBLISHED = 'published';

    public function __construct(private Connection $db)
    {
    }

    public function summary(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $total = (int) (new Query())->from('entities')->count('*', $this->db);
        $cooldown = $this->count(self::COOLDOWN, $now);
        $held = $this->count(self::HELD, $now);

        $cursor = (new Query())->from('mirror_state')->where(['name' => MirrorConverter::CURSOR])->one($this->db);
        $queue = (new Query())->select(['n' => 'COUNT(*)', 'oldest' => 'MIN(created_at)'])->from('mirror_changes')->one($this->db);
        $converted = (new Query())->from('mirror_state')->where(['name' => [MirrorConverter::CURSOR, MirrorConverter::APPLIED]])->max('updated_at', $this->db);
        $indexed = (new Query())->from('entities')->max('indexed_at', $this->db);
        $next = (new Query())->from('entities')->where(['>', 'publish_at', $now])->min('publish_at', $this->db);
        $soon = fn(string $until) => (int) (new Query())->from('entities')->where(['and', ['>', 'publish_at', $now], ['<=', 'publish_at', $until]])->count('*', $this->db);

        return [
            'computed_at' => $now,
            'total' => $total,
            'published' => max(0, $total - $cooldown - $held),
            'cooldown' => $cooldown,
            'held' => $held,
            'next_release_at' => $next,
            'live_within_1h' => $soon(gmdate('Y-m-d H:i:s', time() + 3600)),
            'live_within_24h' => $soon(gmdate('Y-m-d H:i:s', time() + 86400)),
            'last_converted_at' => $converted,
            'newest_indexed_at' => $indexed,
            'cursor_id' => $cursor ? (int) $cursor['value'] : 0,
            'cursor_at' => $cursor['updated_at'] ?? null,
            'queued_changes' => (int) $queue['n'],
            'queued_since' => $queue['oldest'],
        ];
    }

    public function count(string $kind, ?string $now = null, string $q = ''): int
    {
        return (int) $this->scoped($kind, $now ?? gmdate('Y-m-d H:i:s'), $q)->count('*', $this->db);
    }

    public function list(string $kind, int $page, int $size, string $q = ''): array
    {
        $query = $this->scoped($kind, gmdate('Y-m-d H:i:s'), $q)
            ->select(['entities.id', 'state', 'state_filing_number', 'name', 'entity_type', 'indexed_at', 'publish_at', 'publish_override'])
            ->limit($size)
            ->offset(max(0, ($page - 1) * $size));
        return match ($kind) {
            self::COOLDOWN => $query->orderBy(['publish_at' => SORT_ASC, 'indexed_at' => SORT_ASC, 'entities.id' => SORT_ASC])->all($this->db),
            self::PUBLISHED => $query->orderBy(['publish_at' => SORT_DESC, 'indexed_at' => SORT_DESC, 'entities.id' => SORT_DESC])->all($this->db),
            default => $query->orderBy(['indexed_at' => SORT_DESC, 'entities.id' => SORT_DESC])->all($this->db),
        };
    }

    public function setPublish(array $ids, string $mode, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn(int $i) => $i > 0)));
        if (!$ids || !in_array($mode, ['release', 'hold'], true)) {
            return 0;
        }
        $now = gmdate('Y-m-d H:i:s');
        return $this->db->createCommand()->update('entities', [
            'publish_at' => $mode === 'release' ? $now : null,
            'publish_override' => $mode,
            'publish_override_at' => $now,
            'publish_override_by' => $userId,
        ], ['id' => $ids])->execute();
    }

    private function scoped(string $kind, string $now, string $q): Query
    {
        $query = (new Query())->from('entities');
        match ($kind) {
            self::HELD => $query->where(['publish_at' => null]),
            self::PUBLISHED => $query->where(['<=', 'publish_at', $now]),
            default => $query->where(['>', 'publish_at', $now]),
        };
        $q = trim($q);
        if ($q === '') {
            return $query;
        }
        $matches = EntityRepository::keywordMatches($q, false, 3);
        if ($matches !== null) {
            return $query->innerJoin(['m' => $matches], 'm.id = entities.id');
        }
        $key = preg_replace('/[^a-z0-9]/', '', strtolower($q));
        $match = ['or', ['like', 'name', addcslashes($q, '%_\\') . '%', false]];
        if (strlen($key) >= 3) {
            $match[] = ['url_key' => $key];
        }
        return $query->andWhere($match);
    }
}
