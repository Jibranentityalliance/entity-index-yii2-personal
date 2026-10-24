<?php

namespace app\services;

use app\helpers\States;
use app\models\Entity;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\db\Query;

/**
 * Read queries behind the public pages (search, daily lists, per-state stats, related companies).
 * All results are plain arrays, shaped like the old Next.js DataSource.
 */
class EntityRepository
{
    private const SUMMARY_COLS = ['e.id', 'e.state', 'e.url_key', 'e.slug', 'e.name', 'e.entity_type', 'e.status', 'e.filed_at', 'e.indexed_at', 'e.county'];

    /** Words MySQL/InnoDB FULLTEXT ignores; those, and terms under 3 chars, fall back to LIKE. */
    private const FT_STOPWORDS = ['a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how', 'i', 'in', 'is', 'it',
        'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www'];

    private const CACHE_TTL = 300;

    /**
     * @param array{q?:?string,state?:?string,type?:?string,status?:?string,county?:?string,from?:?string,to?:?string,page?:int,pageSize?:int} $p
     * @return array{total:int,page:int,pageSize:int,items:array[]}
     */
    public static function search(array $p): array
    {
        $q = self::base();
        $q->select(self::SUMMARY_COLS);

        if (!empty($p['state'])) {
            $q->andWhere(['e.state' => strtoupper($p['state'])]);
        }
        if (!empty($p['type'])) {
            $q->andWhere(['e.entity_type' => $p['type']]);
        }
        if (!empty($p['status'])) {
            $q->andWhere(['e.status' => $p['status']]);
        }
        if (!empty($p['county'])) {
            $q->andWhere(['e.county' => $p['county']]);
        }
        if (!empty($p['from']) && self::isDay($p['from'])) {
            $q->andWhere(['>=', 'e.filed_at', $p['from'] . ' 00:00:00']);
        }
        if (!empty($p['to']) && self::isDay($p['to'])) {
            $q->andWhere(['<', 'e.filed_at', self::nextDay($p['to'])]);
        }

        $norm = self::normalize($p['q'] ?? '');
        $terms = $norm === '' ? [] : explode(' ', $norm);
        $order = ['e.filed_at' => SORT_DESC];
        $orderParams = [];
        if ($terms) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower($p['q']));
            $nameMatch = self::termsCondition('e.name', $terms, 'n');
            $personMatch = new Expression('EXISTS (SELECT 1 FROM people pe WHERE pe.entity_id = e.id AND ' . self::termsSql('pe.name', $terms, 'p', $params1) . ')', $params1);
            $agentMatch = new Expression('EXISTS (SELECT 1 FROM registered_agents ra WHERE ra.id = e.registered_agent_id AND ' . self::termsSql('ra.name', $terms, 'a', $params2) . ')', $params2);
            $conds = ['or', $nameMatch, $personMatch, $agentMatch];
            if (strlen($key) >= 5) {
                $conds[] = ['e.url_key' => $key];
            }
            $q->andWhere($conds);
            // Filing-number match first, then names starting with the query, then everything else.
            $orderParams = [':ukey' => $key, ':starts' => addcslashes($norm, '%_\\') . '%'];
            $order = '(CASE WHEN e.url_key = :ukey THEN 3 WHEN e.name LIKE :starts THEN 2 ELSE 1 END) DESC, e.filed_at DESC';
        }
        return self::paginate($q, $order, $p['page'] ?? 1, $p['pageSize'] ?? 25, $orderParams);
    }

    /** Most recent calendar day (YYYY-MM-DD, UTC) that has any filings, optionally per state. */
    public static function latestFilingDay(?string $state = null): ?string
    {
        $key = 'ei.latestday.' . ($state ?? 'all');
        return self::remember($key, function () use ($state) {
            $q = (new Query())->from('entities');
            if ($state) {
                $q->where(['state' => strtoupper($state)]);
            }
            $max = $q->max('filed_at');
            return $max ? substr($max, 0, 10) : null;
        });
    }

    /**
     * Per-state counters. Median lag (filed -> searchable) covers the 7 days ending on the latest filing day.
     * @return array<string, array{state:string,total:int,latestDayCount:int,last7Days:int,medianLagHours:?float,lastIndexedAt:?string}>
     */
    public static function stateStats(): array
    {
        return self::remember('ei.statestats', function () {
            $latest = self::latestFilingDay();
            $stats = [];
            $totals = (new Query())->select(['state', 'total' => 'COUNT(*)', 'last' => 'MAX(indexed_at)'])->from('entities')->groupBy('state')->all();
            foreach ($totals as $r) {
                $stats[$r['state']] = ['state' => $r['state'], 'total' => (int) $r['total'], 'latestDayCount' => 0, 'last7Days' => 0,
                    'medianLagHours' => null, 'lastIndexedAt' => $r['last']];
            }
            if ($latest) {
                $weekStart = (new \DateTimeImmutable($latest, new \DateTimeZone('UTC')))->modify('-6 days')->format('Y-m-d');
                $rows = (new Query())->select(['state', 'filed_at', 'indexed_at'])->from('entities')
                    ->where(['>=', 'filed_at', $weekStart . ' 00:00:00'])->each(1000);
                $lags = [];
                foreach ($rows as $r) {
                    $s = $r['state'];
                    $stats[$s]['last7Days']++;
                    if (substr($r['filed_at'], 0, 10) === $latest) {
                        $stats[$s]['latestDayCount']++;
                    }
                    $lags[$s][] = (strtotime($r['indexed_at'] . ' UTC') - strtotime($r['filed_at'] . ' UTC')) / 3600;
                }
                foreach ($lags as $s => $xs) {
                    sort($xs);
                    $m = intdiv(count($xs), 2);
                    $stats[$s]['medianLagHours'] = count($xs) % 2 ? $xs[$m] : ($xs[$m - 1] + $xs[$m]) / 2;
                }
            }
            return $stats;
        });
    }

    /**
     * The 7 days ending on the latest filing day: companies filed, states they were filed in, and how many of their
     * people (agent, officers) have an address with every part (street, city, state, ZIP).
     * @return array{from:?string,to:?string,companies:int,states:int,addresses:int}
     */
    public static function weekOverview(): array
    {
        $stats = self::stateStats();
        $latest = self::latestFilingDay();
        $active = array_filter($stats, fn($s) => $s['last7Days'] > 0);
        return [
            'from' => $latest ? (new \DateTimeImmutable($latest, new \DateTimeZone('UTC')))->modify('-6 days')->format('Y-m-d') : null,
            'to' => $latest,
            'companies' => array_sum(array_column($stats, 'last7Days')),
            'states' => count(array_intersect_key($active, States::all())),
            'addresses' => (int) self::remember('ei.weekaddresses', function () {
                $latest = self::latestFilingDay();
                if (!$latest) {
                    return 0;
                }
                $weekStart = (new \DateTimeImmutable($latest, new \DateTimeZone('UTC')))->modify('-6 days')->format('Y-m-d');
                $q = (new Query())->from(['p' => 'people'])->innerJoin(['e' => 'entities'], 'e.id = p.entity_id')
                    ->where(['>=', 'e.filed_at', $weekStart . ' 00:00:00']);
                foreach (['street', 'city', 'state', 'zip'] as $col) {
                    $q->andWhere(['<>', "p.$col", '']); // also false for NULL
                }
                return $q->count();
            }),
        ];
    }

    public static function totalCount(): int
    {
        return (int) self::remember('ei.total', fn() => (new Query())->from('entities')->count());
    }

    public static function find(string $state, string $urlKey): ?Entity
    {
        return Entity::find()->where(['state' => strtoupper($state), 'url_key' => strtolower($urlKey)])
            ->with(['agent', 'people', 'filings'])->one();
    }

    /** Same state + county (+ same NAICS first), newest first. */
    public static function related(Entity $e, int $limit = 8): array
    {
        $out = [];
        $base = fn(): ActiveQuery => self::base()->select(self::SUMMARY_COLS)
            ->where(['e.state' => $e->state, 'e.county' => $e->county])->andWhere(['<>', 'e.id', $e->id])
            ->orderBy(['e.filed_at' => SORT_DESC])->limit($limit);
        if ($e->naics_guess) {
            $out = $base()->andWhere(['e.naics_guess' => $e->naics_guess])->asArray()->all();
        }
        if (count($out) < $limit) {
            $have = array_column($out, 'id');
            $more = $base()->andFilterWhere(['not in', 'e.id', $have])->limit($limit - count($out))->asArray()->all();
            $out = array_merge($out, $more);
        }
        return $out;
    }

    // ------------------------------------------------------------------ helpers

    private static function base(): ActiveQuery
    {
        return Entity::find()->alias('e')->asArray();
    }

    private static function paginate(ActiveQuery $q, array|string $order, $page, $pageSize, array $orderParams = []): array
    {
        $pageSize = min(max((int) $pageSize, 1), 100);
        $page = max((int) $page, 1);
        $total = (int) (clone $q)->select('COUNT(*)')->orderBy(null)->scalar();
        $items = $q->orderBy($order)->addParams($orderParams)->offset(($page - 1) * $pageSize)->limit($pageSize)->all();
        return ['total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'items' => $items];
    }

    /** Lowercase, letters and digits only, single spaces. Also strips FULLTEXT operators. */
    private static function normalize(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', strtolower($s))));
    }

    private static function termsCondition(string $col, array $terms, string $prefix): Expression
    {
        return new Expression(self::termsSql($col, $terms, $prefix, $params), $params);
    }

    /**
     * SQL requiring every term to match `$col`: FULLTEXT prefix match for normal words,
     * LIKE for short words and InnoDB stopwords (which FULLTEXT would silently ignore).
     */
    private static function termsSql(string $col, array $terms, string $prefix, &$params): string
    {
        $params = [];
        $parts = [];
        foreach ($terms as $i => $t) {
            $ph = ":{$prefix}{$i}";
            if (strlen($t) < 3 || in_array($t, self::FT_STOPWORDS, true)) {
                $parts[] = "$col LIKE $ph";
                $params[$ph] = '%' . addcslashes($t, '%_\\') . '%';
            } else {
                $parts[] = "MATCH($col) AGAINST ($ph IN BOOLEAN MODE)";
                $params[$ph] = '+' . $t . '*';
            }
        }
        return '(' . implode(' AND ', $parts) . ')';
    }

    private static function isDay(string $d): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
    }

    private static function nextDay(string $day): string
    {
        return (new \DateTimeImmutable($day, new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    }

    private static function remember(string $key, callable $fn)
    {
        $cache = Yii::$app->cache;
        $hit = $cache->get($key);
        if ($hit !== false) {
            return $hit === '__null__' ? null : $hit;
        }
        $val = $fn();
        $cache->set($key, $val ?? '__null__', self::CACHE_TTL);
        return $val;
    }
}
