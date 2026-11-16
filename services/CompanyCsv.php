<?php

namespace app\services;

class CompanyCsv
{
    public const COLUMNS = [
        'Company', 'State', 'Filing number', 'Entity type', 'Status', 'Filed', 'County', 'Principal address', 'Mailing address',
        'Registered agent', 'Registered agent address', 'Registered agent phone', 'Officers', 'Officer phones', 'Industry (estimated)', 'Source URL',
    ];

    public static function line(array $e): array
    {
        $agent = $e['registered_agent'];
        $officers = array_map(fn(array $p) => $p['name'] . ($p['title'] ? ' (' . $p['title'] . ')' : ''), $e['people']);
        $phones = array_map(fn(array $p) => $p['name'] . ': ' . $p['phone'], array_filter($e['people'], fn(array $p) => $p['phone'] !== null));
        return array_map(fn($v) => self::safe($v), [
            $e['name'], $e['state'], $e['filing_number'], $e['entity_type'], $e['status'],
            $e['filed_at'] ? substr($e['filed_at'], 0, 10) : null,
            $e['county'], $e['principal_address'], $e['mailing_address'],
            $agent['name'] ?? null, $agent['address']['full'] ?? null, $agent['phone'] ?? null,
            implode('; ', $officers), implode('; ', $phones),
            $e['industry']['label'] ?? null, $e['source']['url'],
        ]);
    }

    private static function safe(?string $v): string
    {
        $v = (string) $v;
        return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;
    }
}
