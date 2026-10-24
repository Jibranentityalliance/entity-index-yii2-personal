<?php

namespace app\helpers;

/** The 51 jurisdictions, loaded from data/states.json (name, slug, planned collection method). */
class States
{
    public const ENTITY_TYPES = [
        'LLC', 'Corporation', 'Foreign LLC', 'Foreign Corporation', 'Nonprofit Corporation', 'LP', 'LLP',
    ];

    /** Where a state's filings come from, by collection method: [label, badge colour]. */
    public const SOURCES = ['api' => ['Open-data API', 'success'], 'file' => ['Bulk file', 'success'], 'website' => ['Website', 'secondary']];
    /** Feed health: on time = median lag of 24h or less. [label, badge colour] */
    public const HEALTH = ['on_time' => ['On time', 'success'], 'delayed' => ['Delayed', 'warning'], 'not_live' => ['Not live', 'danger']];
    /** Median lag ranges, in hours: [label, from (exclusive), to (inclusive)]. */
    public const LAGS = ['24' => ['24h or less', -INF, 24], '48' => ['24–48h', 24, 48], 'over48' => ['Over 48h', 48, INF]];

    /** @var array<string, array{code:string,name:string,slug:string,method:string}>|null */
    private static ?array $byCode = null;

    /** @return array<string, array{code:string,name:string,slug:string,method:string}> keyed by code, sorted by name */
    public static function all(): array
    {
        if (self::$byCode === null) {
            $raw = json_decode((string) file_get_contents(\Yii::getAlias('@app/data/states.json')), true, 512, JSON_THROW_ON_ERROR);
            $rows = [];
            foreach ($raw as $s) {
                $rows[$s['code']] = ['code' => $s['code'], 'name' => $s['name'], 'slug' => $s['slug'], 'method' => $s['method']];
            }
            uasort($rows, fn($a, $b) => strcmp($a['name'], $b['name']));
            self::$byCode = $rows;
        }
        return self::$byCode;
    }

    public static function byCode(string $code): ?array
    {
        return self::all()[strtoupper($code)] ?? null;
    }

    public static function name(string $code): string
    {
        return self::byCode($code)['name'] ?? $code;
    }

    /** Accepts "fl", "FL" or "florida". */
    public static function resolve(string $param): ?array
    {
        $p = strtolower(urldecode($param));
        if (strlen($p) === 2) {
            return self::byCode($p);
        }
        foreach (self::all() as $s) {
            if ($s['slug'] === $p) {
                return $s;
            }
        }
        return null;
    }

    /** SOURCES key for a jurisdiction (states still collected from the website are 'scrape' or 'tbd' in states.json). */
    public static function source(array $state): string
    {
        return in_array($state['method'], ['api', 'file'], true) ? $state['method'] : 'website';
    }

    /** HEALTH key for a jurisdiction's per-state counters (EntityRepository::stateStats), null when nothing is indexed yet. */
    public static function health(?array $stats): string
    {
        if (!$stats) {
            return 'not_live';
        }
        $lag = $stats['medianLagHours'] ?? null;
        return $lag !== null && $lag <= 24 ? 'on_time' : 'delayed';
    }
}
