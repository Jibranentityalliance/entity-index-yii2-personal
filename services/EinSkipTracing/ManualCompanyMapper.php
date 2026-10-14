<?php

namespace app\services\EinSkipTracing;

/**
 * Turns one `manual_companies` row (EIN skip-tracing DB) into rows for `ein_companies` and `ein_company_people`.
 * Pure: no database access, so it can be tested on fixture rows.
 *
 * `data` has changed shape over time. The 2024 rows only have company/registered_agent/officers with basic fields;
 * current rows add full_name, phone, full/mailing addresses, info-found flags, the address `source` and `more_info[]`.
 * Every key is therefore optional. Dates come in many formats (8/27/24, 10-13-2026, 5-Sep-2024, junk); unparseable
 * dates are stored as NULL next to the raw text. Keys this mapper does not know are reported as warnings so the
 * schema can be extended, never silently dropped.
 */
class ManualCompanyMapper
{
    /** Column lengths of the target tables; longer values are truncated. */
    private const COMPANY_LEN = [
        'state' => 2, 'document_number' => 64, 'name' => 255, 'entity_type' => 150, 'date_filed_raw' => 64, 'effective_date_raw' => 64,
        'company_url' => 2048, 'no_officer_reason' => 64,
    ];
    private const PERSON_LEN = [
        'first_name' => 150, 'last_name' => 150, 'full_name' => 255, 'title' => 100, 'phone' => 32, 'full_address' => 500,
        'address' => 255, 'city' => 100, 'state' => 32, 'zip_code' => 10, 'mailing_full_address' => 500, 'mailing_address' => 255,
        'mailing_city' => 100, 'mailing_state' => 32, 'mailing_zip_code' => 10, 'address_source' => 32,
    ];

    private const TOP_KEYS = ['company', 'registered_agent', 'officers', 'more_info'];
    private const COMPANY_KEYS = ['state', 'name', 'document_number', 'type', 'date_filed', 'effective_date', 'company_url', 'status',
        'some_info_found', 'no_info_found'];
    private const PERSON_KEYS = ['ra', 'fname', 'lname', 'full_name', 'o_title', 'phone', 'full_address', 'address', 'city', 'state',
        'zip_code', 'mailing_full_address', 'mailing_address', 'mailing_city', 'mailing_state', 'mailing_zip_code', 'some_info_found',
        'no_info_found', 'is_agent_company', 'source'];
    private const MORE_INFO_KEYS = ['no_officer_reason', 'company_data_found'];

    /** Tried in order; month-first because the source is US data. Results outside 1800..2100 are rejected (e.g. "8/27/24" read as year 24). */
    private const DATE_FORMATS = ['m/d/Y', 'm-d-Y', 'Y-m-d', 'm/d/y', 'm-d-y', 'd-M-Y', 'd-M-y', 'd M Y', 'M d, Y', 'F d, Y', 'M d Y', 'F d Y'];

    /**
     * @param array $row source columns: id, user_id, data (JSON text), ignored_validation_count, qualified, processed, date_added
     * @return array{company: array, people: array[], warnings: string[]}
     * @throws InvalidSourceRowException when `data` is not a JSON object
     */
    public function map(array $row): array
    {
        $id = (int) $row['id'];
        try {
            $data = json_decode((string) $row['data'], true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidSourceRowException("manual_companies.id=$id: data is not valid JSON ({$e->getMessage()})");
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new InvalidSourceRowException("manual_companies.id=$id: data is not a JSON object");
        }

        $warnings = [];
        $this->unknownKeys($data, self::TOP_KEYS, '$', $warnings);
        $c = $this->obj($data['company'] ?? null, '$.company', $warnings);
        $this->unknownKeys($c, self::COMPANY_KEYS, '$.company', $warnings);

        $moreInfo = $data['more_info'] ?? [];
        if (is_array($moreInfo) && $moreInfo !== [] && !array_is_list($moreInfo)) {
            $moreInfo = [$moreInfo];
        }
        $moreInfo = is_array($moreInfo) ? $moreInfo : [];
        if (count($moreInfo) > 1) {
            $warnings[] = '$.more_info has ' . count($moreInfo) . ' entries; only the first is stored';
        }
        $info = $this->obj($moreInfo[0] ?? null, '$.more_info[0]', $warnings);
        $this->unknownKeys($info, self::MORE_INFO_KEYS, '$.more_info[0]', $warnings);

        $dateFiledRaw = $this->text($c['date_filed'] ?? null);
        $effectiveRaw = $this->text($c['effective_date'] ?? null);
        $state = $this->text($c['state'] ?? null);

        $company = $this->fit([
            'source_id' => $id,
            'source_user_id' => $this->int($row['user_id'] ?? null),
            'state' => $state === null ? null : strtoupper($state),
            'document_number' => $this->text($c['document_number'] ?? null),
            'name' => $this->text($c['name'] ?? null),
            'entity_type' => $this->text($c['type'] ?? null),
            'is_active' => $this->bool($c['status'] ?? null),
            'date_filed' => $this->date($dateFiledRaw),
            'date_filed_raw' => $dateFiledRaw,
            'effective_date' => $this->date($effectiveRaw),
            'effective_date_raw' => $effectiveRaw,
            'company_url' => $this->text($c['company_url'] ?? null),
            'some_info_found' => $this->bool($c['some_info_found'] ?? null),
            'no_info_found' => $this->bool($c['no_info_found'] ?? null),
            'company_data_found' => $this->bool($info['company_data_found'] ?? null),
            'no_officer_reason' => $this->text($info['no_officer_reason'] ?? null),
            'qualified' => $this->int($row['qualified'] ?? null),
            'processed' => $this->int($row['processed'] ?? null),
            'ignored_validation_count' => $this->int($row['ignored_validation_count'] ?? null),
            'source_date_added' => $this->text($row['date_added'] ?? null),
        ], self::COMPANY_LEN);

        $people = [];
        $agent = $this->obj($data['registered_agent'] ?? null, '$.registered_agent', $warnings);
        if ($p = $this->person($agent, 'registered_agent', 0, '$.registered_agent', $warnings)) {
            $people[] = $p;
        }
        $officers = $data['officers'] ?? [];
        if (!is_array($officers) || ($officers !== [] && !array_is_list($officers))) {
            $warnings[] = '$.officers is not a list; ignored';
            $officers = [];
        }
        foreach (array_values($officers) as $i => $o) {
            $o = $this->obj($o, "\$.officers[$i]", $warnings);
            if ($p = $this->person($o, 'officer', $i, "\$.officers[$i]", $warnings)) {
                $people[] = $p;
            }
        }

        return ['company' => $company, 'people' => $people, 'warnings' => $warnings];
    }

    /** A person row, or null when the object carries no data at all. */
    private function person(array $p, string $role, int $position, string $path, array &$warnings): ?array
    {
        if ($p === []) {
            return null;
        }
        $this->unknownKeys($p, self::PERSON_KEYS, $path, $warnings);
        $first = $this->text($p['fname'] ?? null);
        $last = $this->text($p['lname'] ?? null);
        $state = $this->text($p['state'] ?? null);
        $mailingState = $this->text($p['mailing_state'] ?? null);
        $row = [
            'role' => $role,
            'position' => $position,
            'first_name' => $first,
            'last_name' => $last,
            'full_name' => $this->text($p['full_name'] ?? null) ?? $this->text(trim("$first $last")),
            'title' => $this->text($p['o_title'] ?? null),
            'is_ra' => $this->bool($p['ra'] ?? null),
            'is_agent_company' => $this->bool($p['is_agent_company'] ?? null),
            'some_info_found' => $this->bool($p['some_info_found'] ?? null),
            'no_info_found' => $this->bool($p['no_info_found'] ?? null),
            'phone' => $this->text($p['phone'] ?? null),
            'full_address' => $this->text($p['full_address'] ?? null),
            'address' => $this->text($p['address'] ?? null),
            'city' => $this->text($p['city'] ?? null),
            'state' => $state === null ? null : strtoupper($state),
            'zip_code' => $this->zip($p['zip_code'] ?? null),
            'mailing_full_address' => $this->text($p['mailing_full_address'] ?? null),
            'mailing_address' => $this->text($p['mailing_address'] ?? null),
            'mailing_city' => $this->text($p['mailing_city'] ?? null),
            'mailing_state' => $mailingState === null ? null : strtoupper($mailingState),
            'mailing_zip_code' => $this->zip($p['mailing_zip_code'] ?? null),
            'address_source' => $this->text($p['source'] ?? null),
        ];
        $hasData = array_filter(array_diff_key($row, ['role' => 1, 'position' => 1]), fn($v) => $v !== null) !== [];
        return $hasData ? $this->fit($row, self::PERSON_LEN) : null;
    }

    /** The value as an associative array; anything else (missing, scalar, list) becomes [] with a warning when it was present. */
    private function obj($v, string $path, array &$warnings): array
    {
        if ($v === null || $v === []) {
            return [];
        }
        if (is_array($v) && !array_is_list($v)) {
            return $v;
        }
        $warnings[] = "$path is not an object; ignored";
        return [];
    }

    private function unknownKeys(array $obj, array $known, string $path, array &$warnings): void
    {
        foreach (array_diff(array_keys($obj), $known) as $k) {
            $warnings[] = "unknown key $path.$k";
        }
    }

    /** Trimmed text with runs of whitespace collapsed; empty becomes null. Nested values are kept as JSON text. */
    private function text($v): ?string
    {
        if ($v === null || is_bool($v)) {
            return null;
        }
        if (is_array($v)) {
            return $v === [] ? null : json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $v = trim(preg_replace('/\s+/u', ' ', (string) $v) ?? (string) $v);
        return $v === '' ? null : $v;
    }

    private function int($v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    /** true/false, 1/0, "1"/"0", "true"/"false", "yes"/"no"; anything else is null. */
    private function bool($v): ?bool
    {
        if (is_bool($v)) {
            return $v;
        }
        return is_scalar($v) ? filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
    }

    /** Y-m-d, or null when the text is not a recognisable date. */
    public function date(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        foreach (self::DATE_FORMATS as $f) {
            $d = \DateTimeImmutable::createFromFormat('!' . $f, $raw, new \DateTimeZone('UTC'));
            $errors = \DateTimeImmutable::getLastErrors();
            if ($d === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                continue;
            }
            $year = (int) $d->format('Y');
            if ($year >= 1800 && $year <= 2100) {
                return $d->format('Y-m-d');
            }
        }
        return null;
    }

    /** "12345" or "12345-6789" for US ZIPs (keeps leading zeros; "20155 - 4409" → "20155-4409"); other text as is. */
    private function zip($v): ?string
    {
        $v = $this->text($v);
        if ($v === null) {
            return null;
        }
        $compact = preg_replace('/\s+/', '', $v);
        if (preg_match('/^(\d{5})-?(\d{4})?$/', $compact, $m)) {
            return isset($m[2]) ? "$m[1]-$m[2]" : $m[1];
        }
        return $v;
    }

    /** Truncates string values to the column lengths in $len. */
    private function fit(array $row, array $len): array
    {
        foreach ($len as $k => $max) {
            if (is_string($row[$k] ?? null) && mb_strlen($row[$k]) > $max) {
                $row[$k] = mb_substr($row[$k], 0, $max);
            }
        }
        return $row;
    }
}
