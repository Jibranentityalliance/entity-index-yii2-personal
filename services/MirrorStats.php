<?php

namespace app\services;

use app\helpers\States;
use app\services\EinSkipTracing\InvalidSourceRowException;
use app\services\EinSkipTracing\ManualCompanyMapper;
use yii\db\Connection;
use yii\db\Query;

/**
 * Landing-page numbers computed from the local replica of the portal's `manual_companies` table (MIRROR_DB on the
 * website's own MariaDB server, kept current by MariaDB replication).
 *
 * Heavy work happens only in `php yii stats/refresh` (cron); the result is one JSON row in `site_stats` that the
 * home page reads. The web request never touches the mirror.
 *
 * - total: an exact COUNT(*) at most once a day, plus the rows added since (cheap primary-key range count).
 * - recent: walks back from the newest id in batches until 30 days of rows are covered (ids follow date_added).
 * - `date_added` is in the portal server's time zone (MIRROR_TZ); everything stored here is UTC.
 * - The activity feed keeps only entity type, state and time: no names or people, the home page is public.
 */
class MirrorStats
{
    public const NAME = 'landing';
    private const BASE_NAME = 'landing.total_base';
    private const BATCH = 5000;
    private const MAX_RECENT_ROWS = 500000;
    private const SERIES_DAYS = 14;
    private const FEED_SIZE = 8;

    public function __construct(
        private Connection $db,
        private string $mirrorDb,
        private \DateTimeZone $sourceTz,
        private ?\DateTimeImmutable $now = null,
    ) {
        $this->now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /** Computes the numbers and stores them in site_stats. Returns what was stored. */
    public function refresh(): array
    {
        $stats = $this->compute();
        $this->save(self::NAME, $stats);
        return $stats;
    }

    /** The last stored numbers, or null when stats/refresh has never run. */
    public static function load(Connection $db): ?array
    {
        $json = (new Query())->select('data')->from('site_stats')->where(['name' => self::NAME])->scalar($db);
        return $json === false ? null : json_decode($json, true);
    }

    public function compute(): array
    {
        $table = $this->db->quoteTableName($this->mirrorDb . '.manual_companies');
        $nowUtc = $this->now->setTimezone(new \DateTimeZone('UTC'));
        $today = $this->now->setTimezone($this->sourceTz)->setTime(0, 0);

        $days = [];
        for ($i = self::SERIES_DAYS - 1; $i >= 0; $i--) {
            $days[$today->modify("-$i days")->format('Y-m-d')] = 0;
        }
        $cut24h = $nowUtc->modify('-24 hours');
        $cut7d = $nowUtc->modify('-7 days');
        $cut30d = $nowUtc->modify('-30 days');

        $mapper = new ManualCompanyMapper();
        $last24h = $last7d = $scanned = 0;
        $states30 = $states7 = $lags = $feed = [];
        $newestAt = null;
        $before = PHP_INT_MAX;
        $done = false;
        while (!$done && $scanned < self::MAX_RECENT_ROWS) {
            $rows = $this->db->createCommand("SELECT id, data, date_added FROM $table WHERE id < :before ORDER BY id DESC LIMIT " . self::BATCH,
                [':before' => $before])->queryAll();
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $scanned++;
                $before = (int) $row['id'];
                $added = $this->sourceTime($row['date_added']);
                if ($added === null) {
                    continue;
                }
                if ($added < $cut30d) {
                    $done = true; // ids follow date_added, so everything further back is older
                    break;
                }
                $newestAt ??= $added;
                try {
                    $c = $mapper->map($row)['company'];
                } catch (InvalidSourceRowException) {
                    continue;
                }
                $state = $c['state'] !== null && States::byCode($c['state']) ? $c['state'] : null;
                if ($state) {
                    $states30[$state] = true;
                }
                $day = $added->setTimezone($this->sourceTz)->format('Y-m-d');
                if (isset($days[$day])) {
                    $days[$day]++;
                }
                if ($added >= $cut24h) {
                    $last24h++;
                }
                if ($added >= $cut7d) {
                    $last7d++;
                    if ($state) {
                        $states7[$state] = ($states7[$state] ?? 0) + 1;
                    }
                    if ($c['date_filed'] !== null) {
                        $lag = (int) (new \DateTimeImmutable($c['date_filed'], $this->sourceTz))->diff($added->setTimezone($this->sourceTz)->setTime(0, 0))->format('%r%a');
                        if ($lag >= 0 && $lag <= 365) {
                            $lags[] = $lag;
                        }
                    }
                }
                if (count($feed) < self::FEED_SIZE && $state) {
                    $feed[] = ['type' => self::entityKind($c['entity_type']), 'state' => $state, 'at' => $added->format('Y-m-d\TH:i:s\Z')];
                }
            }
        }

        arsort($states7);
        $top = [];
        foreach (array_slice($states7, 0, 5, true) as $code => $n) {
            $top[] = ['code' => $code, 'name' => States::name($code), 'count' => $n];
        }
        sort($lags);

        return [
            'computed_at' => $nowUtc->format('Y-m-d\TH:i:s\Z'),
            'newest_at' => $newestAt?->format('Y-m-d\TH:i:s\Z'),
            'total' => $this->total($table),
            'last_24h' => $last24h,
            'last_7d' => $last7d,
            'states_30d' => count($states30),
            'median_lag_days' => $lags ? $lags[intdiv(count($lags), 2)] : null,
            'daily' => array_map(fn($d, $n) => ['day' => $d, 'count' => $n], array_keys($days), $days),
            'top_states_7d' => $top,
            'feed' => $feed,
        ];
    }

    /** Exact count once a day; in between, the stored count plus rows with a higher id. */
    private function total(string $table): int
    {
        $base = (new Query())->select(['data', 'computed_at'])->from('site_stats')->where(['name' => self::BASE_NAME])->one($this->db);
        $fresh = $base && strtotime($base['computed_at'] . ' UTC') > $this->now->getTimestamp() - 86400;
        if ($fresh) {
            $b = json_decode($base['data'], true);
            $added = (int) $this->db->createCommand("SELECT COUNT(*) FROM $table WHERE id > :id", [':id' => $b['max_id']])->queryScalar();
            return $b['count'] + $added;
        }
        $r = $this->db->createCommand("SELECT COUNT(*) AS n, COALESCE(MAX(id), 0) AS max_id FROM $table")->queryOne();
        $this->save(self::BASE_NAME, ['count' => (int) $r['n'], 'max_id' => (int) $r['max_id']]);
        return (int) $r['n'];
    }

    private function save(string $name, array $data): void
    {
        $this->db->createCommand()->upsert('site_stats', [
            'name' => $name,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'computed_at' => $this->now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ])->execute();
    }

    private function sourceTime(?string $v): ?\DateTimeImmutable
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($v, $this->sourceTz))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    /** "LLC", "Foreign LLC", "Corporation", ... from the free-text type in the source. */
    public static function entityKind(?string $type): string
    {
        $t = strtoupper((string) $type);
        $kind = match (true) {
            str_contains($t, 'LLC'), str_contains($t, 'LIMITED LIAB') => 'LLC',
            str_contains($t, 'NONPROFIT'), str_contains($t, 'NON-PROFIT'), str_contains($t, 'NOT FOR PROFIT') => 'Nonprofit',
            str_contains($t, 'PARTNERSHIP'), preg_match('/\bL\.?L?\.?P\b/', $t) === 1 => 'Partnership',
            str_contains($t, 'CORP'), str_contains($t, 'INC') => 'Corporation',
            default => 'Business',
        };
        return (str_contains($t, 'FOREIGN') ? 'Foreign ' : '') . $kind;
    }
}
