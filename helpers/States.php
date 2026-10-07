<?php

namespace app\helpers;

/** The 51 jurisdictions, loaded from data/states.json (name, slug, planned collection method). */
class States
{
    public const ENTITY_TYPES = [
        'LLC', 'Corporation', 'Foreign LLC', 'Foreign Corporation', 'Nonprofit Corporation', 'LP', 'LLP',
    ];

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
}
