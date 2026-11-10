<?php

namespace app\modules\apiv1\serializers;

use app\helpers\Fmt;

class EntitySerializer
{
    // The API is only available on Business, Data license and admin accounts, so officers, addresses and phones
    // are always included. This matches what the portal shows to paid plans.
    public static function entity(array $e, bool $withFilings = false): array
    {
        $agent = null;
        $people = [];
        foreach ($e['people'] ?? [] as $p) {
            if ($p['role'] === 'agent') {
                $agent ??= ['name' => self::text($p['name']), 'commercial' => null, 'address' => self::address($p), 'phone' => self::phone($p['phone'] ?? null)];
                continue;
            }
            $people[] = [
                'name' => self::text($p['name']),
                'role' => self::text($p['role']),
                'title' => self::text($p['title']),
                'address' => self::address($p),
                'phone' => self::phone($p['phone'] ?? null),
            ];
        }
        if ($agent === null && !empty($e['agent'])) {
            $agent = [
                'name' => self::text($e['agent']['name']),
                'commercial' => (bool) $e['agent']['is_commercial_agent'],
                'address' => self::address($e['agent']),
                'phone' => null,
            ];
        }

        $out = [
            'id' => (int) $e['id'],
            'state' => $e['state'] === 'NA' ? null : $e['state'],
            'filing_number' => self::text($e['state_filing_number']),
            'name' => self::text($e['name']),
            'entity_type' => self::text($e['entity_type']),
            'status' => self::text($e['status']),
            'filed_at' => self::time($e['filed_at']),
            'effective_at' => self::time($e['formation_date']),
            'indexed_at' => self::time($e['indexed_at']),
            'county' => self::text($e['county']),
            'principal_address' => self::text($e['principal_address']),
            'mailing_address' => self::text($e['mailing_address']),
            'industry' => $e['naics_guess'] ? ['naics' => $e['naics_guess'], 'label' => self::text($e['naics_label']), 'estimated' => true] : null,
            'registered_agent' => $agent,
            'people' => $people,
            'source' => ['url' => self::text($e['source_url']), 'pulled_at' => self::time($e['indexed_at'])],
        ];
        if ($withFilings) {
            $out['filings'] = array_map(fn(array $f) => [
                'type' => $f['filing_type'],
                'date' => $f['filing_date'],
                'document_url' => self::text($f['document_url']),
            ], $e['filings'] ?? []);
        }
        return $out;
    }

    private static function text(?string $v): ?string
    {
        return $v === null || trim($v) === '' ? null : $v;
    }

    private static function time(?string $v): ?string
    {
        return $v ? gmdate('Y-m-d\TH:i:s\Z', strtotime($v . ' UTC')) : null;
    }

    private static function phone(?string $v): ?string
    {
        return $v === null || trim($v) === '' ? null : Fmt::phone($v);
    }

    private static function address(array $row): ?array
    {
        $full = self::text($row['address'] ?? null);
        $parts = ['street' => self::text($row['street'] ?? null), 'city' => self::text($row['city'] ?? null),
            'state' => self::text($row['state'] ?? null), 'zip' => self::text($row['zip'] ?? null)];
        if ($full === null && !array_filter($parts)) {
            return null;
        }
        return ['full' => $full] + $parts;
    }
}
