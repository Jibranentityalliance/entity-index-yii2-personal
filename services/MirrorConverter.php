<?php

namespace app\services;

use app\helpers\States;
use app\services\EinSkipTracing\InvalidSourceRowException;
use app\services\EinSkipTracing\ManualCompanyMapper;
use yii\db\Connection;
use yii\helpers\Inflector;

/**
 * Turns rows of the replicated portal table `manual_companies` (MIRROR_DB on this server) into the portal's own
 * `entities` + `people` rows.
 *
 * - One company per state + filing number. Repeat submissions update it; the newest source row wins.
 * - Malformed rows are kept: unknown values stay empty/NULL and the pages show "N/A". Without a valid state the
 *   company is stored under state 'NA'; without a filing number its url_key is "r{source_id}".
 * - The registered agent and officers become `people` rows (role agent / officer), with address and phone.
 * - `backfill()` walks the whole table once (resumable); `applyChanges()` replays the ids that the replica triggers
 *   (docs/mirror-triggers.sql) wrote to mirror_changes, including deletes.
 * - Only reads the mirror. Writes go to this app's database.
 */
class MirrorConverter
{
    public const CURSOR = 'backfill_cursor';
    private const ENTITY_COLS = ['source_id', 'state', 'state_filing_number', 'url_key', 'slug', 'name', 'entity_type', 'status',
        'formation_date', 'filed_at', 'indexed_at', 'source_url'];

    private ManualCompanyMapper $mapper;
    private string $table;

    public function __construct(private Connection $db, string $mirrorDb, private \DateTimeZone $sourceTz)
    {
        $this->mapper = new ManualCompanyMapper();
        $this->table = $db->quoteTableName($mirrorDb . '.manual_companies');
    }

    /** Converts the next rows after the stored cursor, in batches. Returns [rows done, cursor]. Safe to stop and rerun. */
    public function backfill(int $batch = 1000, ?int $maxRows = null, ?callable $progress = null): array
    {
        $cursor = $this->state(self::CURSOR);
        $done = 0;
        while ($maxRows === null || $done < $maxRows) {
            $limit = $maxRows === null ? $batch : min($batch, $maxRows - $done);
            $rows = $this->db->createCommand("SELECT * FROM {$this->table} WHERE id > :c ORDER BY id LIMIT $limit", [':c' => $cursor])->queryAll();
            if (!$rows) {
                break;
            }
            $tx = $this->db->beginTransaction();
            $this->applyRows($rows);
            $cursor = (int) end($rows)['id'];
            $this->setState(self::CURSOR, $cursor);
            $tx->commit();
            $done += count($rows);
            if ($progress) {
                $progress($done, $cursor);
            }
        }
        return [$done, $cursor];
    }

    /** Replays changed source ids written by the replica triggers. Returns [changes read, rows applied, rows deleted]. */
    public function applyChanges(int $max = 5000): array
    {
        $changes = $this->db->createCommand('SELECT id, source_id FROM mirror_changes ORDER BY id LIMIT ' . (int) $max)->queryAll();
        if (!$changes) {
            return [0, 0, 0];
        }
        $ids = array_values(array_unique(array_map('intval', array_column($changes, 'source_id'))));
        $rows = $this->db->createCommand("SELECT * FROM {$this->table} WHERE id IN (" . implode(',', $ids) . ') ORDER BY id')->queryAll();
        $found = array_map('intval', array_column($rows, 'id'));
        $gone = array_diff($ids, $found);

        $tx = $this->db->beginTransaction();
        if ($rows) {
            $this->applyRows($rows);
        }
        foreach ($gone as $id) {
            $this->deleteSource($id);
        }
        $this->db->createCommand('DELETE FROM mirror_changes WHERE id <= :id', [':id' => (int) end($changes)['id']])->execute();
        $tx->commit();
        return [count($changes), count($rows), count($gone)];
    }

    /** Pure: the entity and people rows for one source row. */
    public function build(array $row): array
    {
        $id = (int) $row['id'];
        try {
            $m = $this->mapper->map($row);
            $c = $m['company'];
            $people = $m['people'];
        } catch (InvalidSourceRowException) {
            $c = [];
            $people = [];
        }
        $state = isset($c['state']) && States::byCode($c['state']) ? $c['state'] : null;
        $filing = $c['document_number'] ?? null;
        $key = strtolower(substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $filing), 0, 64));
        $name = (string) ($c['name'] ?? '');
        $slug = substr(Inflector::slug($name), 0, 150);
        $indexed = $this->utc($row['date_added'] ?? null);
        // A filing or effective date more than a day ahead of today is a data-entry error (e.g. 2030): unknown, shown as N/A.
        // (A date after the row was added is normal: the portal can record a company before the state's filing date.)
        $tomorrow = gmdate('Y-m-d', time() + 86400);
        $notFuture = fn(?string $d) => $d !== null && $d <= $tomorrow ? $d : null;
        $filed = $notFuture($c['date_filed'] ?? null);
        $effective = $notFuture($c['effective_date'] ?? null);

        $entity = [
            'source_id' => $id,
            'state' => $state ?? 'NA',
            'state_filing_number' => (string) ($filing ?? ''),
            'url_key' => $state && $key !== '' ? $key : 'r' . $id,
            'slug' => $slug !== '' ? $slug : 'company',
            'name' => $name,
            'entity_type' => mb_substr((string) ($c['entity_type'] ?? ''), 0, 64),
            'status' => match ($c['is_active'] ?? null) { true => 'Active', false => 'Inactive', default => '' },
            'formation_date' => $effective ?? $filed,
            'filed_at' => $filed ? $filed . ' 00:00:00' : null,
            'indexed_at' => $indexed,
            'source_url' => isset($c['company_url']) ? mb_substr($c['company_url'], 0, 500) : null,
        ];
        $out = [];
        foreach ($people as $p) {
            $parts = array_filter([$p['address'] ?? null, $p['city'] ?? null, trim(($p['state'] ?? '') . ' ' . ($p['zip_code'] ?? ''))]);
            $out[] = [
                'name' => mb_substr((string) ($p['full_name'] ?? ''), 0, 255),
                'role' => $p['role'] === 'registered_agent' ? 'agent' : 'officer',
                'title' => isset($p['title']) ? mb_substr($p['title'], 0, 150) : null,
                'address' => $p['full_address'] ?? ($parts ? mb_substr(implode(', ', $parts), 0, 500) : null),
                'phone' => $p['phone'] ?? null,
            ];
        }
        return ['entity' => $entity, 'people' => $out];
    }

    /** Upserts a batch of source rows (any order); newest source row per company wins. */
    public function applyRows(array $rows): void
    {
        usort($rows, fn($a, $b) => (int) $a['id'] <=> (int) $b['id']);
        $built = array_map(fn($r) => $this->build($r), $rows);
        $ids = array_map(fn($b) => $b['entity']['source_id'], $built);
        $oldMap = $this->db->createCommand('SELECT source_id, entity_id FROM mirror_map WHERE source_id IN (' . implode(',', $ids) . ')')->queryAll();
        $oldMap = array_column($oldMap, 'entity_id', 'source_id');

        // Upsert companies; a row only overwrites a company when it is at least as new as the one already there.
        $cols = self::ENTITY_COLS;
        $guard = 'VALUES(source_id) >= COALESCE(source_id, 0)';
        $updates = [];
        foreach ($cols as $col) {
            if ($col !== 'source_id' && $col !== 'state' && $col !== 'url_key') {
                $updates[] = "$col = IF($guard, VALUES($col), $col)";
            }
        }
        $updates[] = "updated_at = IF($guard, NOW(), updated_at)";
        $updates[] = 'source_id = GREATEST(COALESCE(source_id, 0), VALUES(source_id))';
        $sql = $this->db->createCommand()->batchInsert('entities', $cols, array_map(fn($b) => array_values($b['entity']), $built))->getRawSql();
        $this->db->createCommand($sql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates))->execute();

        // Which company each row landed in, and whether it is now that company's newest row.
        $pairs = [];
        $params = [];
        foreach ($built as $i => $b) {
            $pairs[] = "(:s$i, :k$i)";
            $params[":s$i"] = $b['entity']['state'];
            $params[":k$i"] = $b['entity']['url_key'];
        }
        $found = $this->db->createCommand('SELECT id, state, url_key, source_id FROM entities WHERE (state, url_key) IN (' . implode(',', $pairs) . ')', $params)->queryAll();
        $byKey = [];
        foreach ($found as $f) {
            $byKey[$f['state'] . '|' . strtolower($f['url_key'])] = $f;
        }

        $mapRows = $winners = [];
        foreach ($built as $b) {
            $f = $byKey[$b['entity']['state'] . '|' . $b['entity']['url_key']];
            $mapRows[] = [$b['entity']['source_id'], (int) $f['id']];
            if ((int) $f['source_id'] === $b['entity']['source_id']) {
                $winners[(int) $f['id']] = $b['people']; // later rows in the batch overwrite earlier ones
            }
        }
        if ($winners) {
            $this->db->createCommand()->delete('people', ['entity_id' => array_keys($winners)])->execute();
            $peopleRows = [];
            foreach ($winners as $entityId => $people) {
                foreach ($people as $p) {
                    $peopleRows[] = [$entityId, $p['name'], $p['role'], $p['title'], $p['address'], $p['phone']];
                }
            }
            if ($peopleRows) {
                $this->db->createCommand()->batchInsert('people', ['entity_id', 'name', 'role', 'title', 'address', 'phone'], $peopleRows)->execute();
            }
        }
        $mapSql = $this->db->createCommand()->batchInsert('mirror_map', ['source_id', 'entity_id'], $mapRows)->getRawSql();
        $this->db->createCommand($mapSql . ' ON DUPLICATE KEY UPDATE entity_id = VALUES(entity_id)')->execute();

        // A row whose state or filing number changed moved to another company: tidy up the one it left.
        foreach ($mapRows as [$sourceId, $entityId]) {
            if (isset($oldMap[$sourceId]) && (int) $oldMap[$sourceId] !== $entityId) {
                $this->afterSourceLeft((int) $oldMap[$sourceId], $sourceId);
            }
        }
    }

    /** The source row was deleted on the portal. */
    public function deleteSource(int $sourceId): void
    {
        $entityId = $this->db->createCommand('SELECT entity_id FROM mirror_map WHERE source_id = :s', [':s' => $sourceId])->queryScalar();
        if ($entityId === false) {
            return;
        }
        $this->db->createCommand()->delete('mirror_map', ['source_id' => $sourceId])->execute();
        $this->afterSourceLeft((int) $entityId, $sourceId);
    }

    /** Removes a company with no source rows left, or rebuilds it from its newest remaining row. */
    private function afterSourceLeft(int $entityId, int $sourceId): void
    {
        $remaining = $this->db->createCommand('SELECT MAX(source_id) FROM mirror_map WHERE entity_id = :e', [':e' => $entityId])->queryScalar();
        if ($remaining === null || $remaining === false) {
            $this->db->createCommand()->delete('entities', ['id' => $entityId])->execute();
            return;
        }
        $current = (int) $this->db->createCommand('SELECT source_id FROM entities WHERE id = :e', [':e' => $entityId])->queryScalar();
        if ($current !== $sourceId) {
            return; // the company still shows a newer row
        }
        $row = $this->db->createCommand("SELECT * FROM {$this->table} WHERE id = :id", [':id' => (int) $remaining])->queryOne();
        if ($row === false) {
            $this->deleteSource((int) $remaining);
            return;
        }
        $this->db->createCommand()->update('entities', ['source_id' => 0], ['id' => $entityId])->execute();
        $this->applyRows([$row]);
    }

    public function state(string $name): int
    {
        return (int) $this->db->createCommand('SELECT value FROM mirror_state WHERE name = :n', [':n' => $name])->queryScalar();
    }

    public function setState(string $name, int $value): void
    {
        $this->db->createCommand()->upsert('mirror_state', ['name' => $name, 'value' => $value, 'updated_at' => gmdate('Y-m-d H:i:s')])->execute();
    }

    private function utc(?string $v): string
    {
        try {
            return $v ? (new \DateTimeImmutable($v, $this->sourceTz))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s');
        } catch (\Exception) {
            return gmdate('Y-m-d H:i:s');
        }
    }
}
