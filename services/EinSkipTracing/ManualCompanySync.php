<?php

namespace app\services\EinSkipTracing;

use Yii;
use yii\db\Connection;

/**
 * Copies new `manual_companies` rows from the EIN skip-tracing database into `ein_companies` / `ein_company_people`.
 *
 * Forward only, by primary key: the first run stores the source's current MAX(id) as the cursor and copies nothing;
 * every later run copies the next rows after the cursor (at most MAX_LIMIT). The source is production, so the only queries
 * sent to it are `SELECT MAX(id)` and a PK range scan with a LIMIT, on a read-only session (config/db_ein_skip_tracing.php).
 *
 * Each source row is written in its own local transaction together with the cursor, so a crash never skips or
 * half-writes a row. A row whose `data` cannot be mapped is logged and skipped so it cannot block the queue.
 */
class ManualCompanySync
{
    public const STATE_NAME = 'manual_companies';
    /** Hard cap on rows per run, for now. */
    public const MAX_LIMIT = 10;

    private ManualCompanyMapper $mapper;

    public function __construct(private Connection $source, private Connection $local, ?ManualCompanyMapper $mapper = null)
    {
        $this->mapper = $mapper ?? new ManualCompanyMapper();
    }

    /**
     * @return array{initialized:bool, cursor:int, fetched:int, inserted:int, updated:int, unchanged:int, failed:int, lag:int, warnings:string[]}
     */
    public function run(int $limit = self::MAX_LIMIT): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $result = ['initialized' => false, 'cursor' => 0, 'fetched' => 0, 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0, 'lag' => 0, 'warnings' => []];

        $cursor = $this->local->createCommand('SELECT last_source_id FROM ein_sync_state WHERE name = :n', [':n' => self::STATE_NAME])->queryScalar();
        if ($cursor === false) {
            $max = $this->sourceMaxId();
            $this->saveState($max, null);
            return ['initialized' => true, 'cursor' => $max] + $result;
        }
        $cursor = (int) $cursor;

        $rows = $this->source->createCommand(
            'SELECT id, user_id, data, ignored_validation_count, qualified, processed, date_added FROM manual_companies'
            . ' WHERE id > :cursor ORDER BY id LIMIT ' . $limit,
            [':cursor' => $cursor]
        )->queryAll();
        $result['fetched'] = count($rows);

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            try {
                $mapped = $this->mapper->map($row);
            } catch (InvalidSourceRowException $e) {
                Yii::error($e->getMessage(), __METHOD__);
                $result['failed']++;
                $this->saveState($id, $e->getMessage());
                $cursor = $id;
                continue;
            }
            foreach ($mapped['warnings'] as $w) {
                $w = "manual_companies.id=$id: $w";
                Yii::warning($w, __METHOD__);
                $result['warnings'][] = $w;
            }
            $result[$this->store($mapped, $this->hash($row))]++;
            $cursor = $id;
        }
        $this->saveState($cursor, null); // records last_run_at even when there was nothing new

        $result['cursor'] = $cursor;
        $result['lag'] = max(0, $this->sourceMaxId() - $cursor);
        return $result;
    }

    /** Writes one company and its people, and moves the cursor to it, in one transaction. Returns 'inserted' | 'updated' | 'unchanged'. */
    private function store(array $mapped, string $hash): string
    {
        $company = $mapped['company'];
        return $this->local->transaction(function (Connection $db) use ($mapped, $company, $hash) {
            $existing = $db->createCommand('SELECT id, source_hash FROM ein_companies WHERE source_id = :s FOR UPDATE', [':s' => $company['source_id']])->queryOne();
            if ($existing && $existing['source_hash'] === $hash) {
                $outcome = 'unchanged';
            } else {
                $values = $company + ['source_hash' => $hash, 'synced_at' => gmdate('Y-m-d H:i:s')];
                if ($existing) {
                    $companyId = (int) $existing['id'];
                    $db->createCommand()->update('ein_companies', $values, ['id' => $companyId])->execute();
                    $db->createCommand()->delete('ein_company_people', ['company_id' => $companyId])->execute();
                    $outcome = 'updated';
                } else {
                    $db->createCommand()->insert('ein_companies', $values)->execute();
                    $companyId = (int) $db->getLastInsertID();
                    $outcome = 'inserted';
                }
                if ($mapped['people']) {
                    $people = array_map(fn($p) => ['company_id' => $companyId] + $p, $mapped['people']);
                    $db->createCommand()->batchInsert('ein_company_people', array_keys($people[0]), array_map('array_values', $people))->execute();
                }
            }
            $this->saveState((int) $company['source_id'], null, $db);
            return $outcome;
        });
    }

    /** Hash of every source value we copy, so a re-synced row is only rewritten when something in it changed. */
    private function hash(array $row): string
    {
        return hash('sha256', json_encode([$row['data'], $row['user_id'], $row['qualified'], $row['processed'], $row['ignored_validation_count'], $row['date_added']]));
    }

    private function sourceMaxId(): int
    {
        return (int) $this->source->createCommand('SELECT MAX(id) FROM manual_companies')->queryScalar();
    }

    private function saveState(int $cursor, ?string $error, ?Connection $db = null): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $values = ['last_source_id' => $cursor, 'last_run_at' => $now];
        if ($error !== null) { // kept until the next error, so it can be inspected later
            $values['last_error'] = $now . ' ' . $error;
        }
        ($db ?? $this->local)->createCommand()->upsert('ein_sync_state', ['name' => self::STATE_NAME] + $values, $values)->execute();
    }
}
