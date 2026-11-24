<?php

namespace app\services\bulk;

use app\modules\apiv1\serializers\EntitySerializer;
use app\services\CompanyCsv;
use app\services\EntityRepository;
use yii\db\Connection;
use yii\helpers\FileHelper;

class BulkBuilder
{
    private const CHUNK = 1000;
    private const BOM = "\xEF\xBB\xBF";
    private const UTC = 'UTC';

    public function __construct(
        private Connection $db,
        private BulkStore $store,
        private string $tmpDir,
        private int $keepDays,
        private int $newDays = 7,
        private int $newWeeks = 4,
    ) {
    }

    public function build(\DateTimeImmutable $at, ?callable $progress = null): array
    {
        $date = $at->setTimezone(new \DateTimeZone(self::UTC))->format('Y-m-d');
        $r = $this->write(BulkCatalog::FULL, $date, null, $progress);
        if ($r['rows'] === 0) {
            return $r;
        }
        $r['pruned'] = $this->prune(BulkCatalog::FULL, $this->daysBefore($date, max(0, $this->keepDays - 1)));
        return $r;
    }

    public function buildNew(\DateTimeImmutable $at): array
    {
        $today = $at->setTimezone(new \DateTimeZone(self::UTC))->setTime(0, 0);
        $out = ['daily' => [], 'weekly' => [], 'pruned' => 0];
        for ($i = $this->newDays; $i >= 1; $i--) {
            $start = $today->modify("-$i days");
            $out['daily'][] = $this->write(BulkCatalog::DAILY, $start->format('Y-m-d'), [$start, $start->modify('+1 day')]);
        }
        $monday = $today->modify('monday this week');
        for ($i = $this->newWeeks; $i >= 1; $i--) {
            $start = $monday->modify("-$i weeks");
            $out['weekly'][] = $this->write(BulkCatalog::WEEKLY, $start->format('Y-m-d'), [$start, $start->modify('+7 days')]);
        }
        $out['pruned'] += $this->prune(BulkCatalog::DAILY, $today->modify("-{$this->newDays} days")->format('Y-m-d'));
        $out['pruned'] += $this->prune(BulkCatalog::WEEKLY, $monday->modify("-{$this->newWeeks} weeks")->format('Y-m-d'));
        return $out;
    }

    private function write(string $kind, string $date, ?array $window, ?callable $progress = null): array
    {
        $work = rtrim($this->tmpDir, '/') . '/' . $kind . '-' . $date . '-' . bin2hex(random_bytes(4));
        FileHelper::createDirectory($work, 0750);
        $prefix = $kind === BulkCatalog::FULL ? '' : 'new-' . $kind . '/';
        $stem = $kind === BulkCatalog::FULL ? 'entityindex-' : 'entityindex-new-';
        $suffix = $kind === BulkCatalog::WEEKLY ? 'week-' . $date : $date;

        $streams = [];
        $rows = [];
        $total = 0;
        try {
            $streams[BulkCatalog::ALL] = $this->open($work, $stem, BulkCatalog::ALL, $suffix);
            foreach ($this->batches($window) as $batch) {
                foreach ($batch as $e) {
                    $line = CompanyCsv::line(EntitySerializer::entity($e));
                    $scope = (string) $e['state'];
                    $streams[$scope] ??= $this->open($work, $stem, $scope, $suffix);
                    fputcsv($streams[$scope]['fp'], $line, ',', '"', '');
                    fputcsv($streams[BulkCatalog::ALL]['fp'], $line, ',', '"', '');
                    $rows[$scope] = ($rows[$scope] ?? 0) + 1;
                    $total++;
                }
                if ($progress) {
                    $progress($total);
                }
            }
            if ($total === 0 && $kind === BulkCatalog::FULL) {
                return ['kind' => $kind, 'date' => $date, 'files' => [], 'rows' => 0];
            }
            $rows[BulkCatalog::ALL] = $total;

            $files = [];
            foreach ($streams as $scope => $s) {
                fclose($s['fp']);
                $streams[$scope]['fp'] = null;
                $key = $prefix . $date . '/' . $s['name'];
                $files[] = [
                    'scope' => $scope,
                    'key' => $key,
                    'name' => $s['name'],
                    'rows' => $rows[$scope],
                    'bytes' => filesize($s['path']),
                    'sha256' => hash_file('sha256', $s['path']),
                ];
                $this->store->put($key, $s['path']);
            }
            foreach ((new BulkCatalog($this->db))->replace($kind, $date, $files) as $stale) {
                $this->store->delete($stale);
            }
            return ['kind' => $kind, 'date' => $date, 'files' => $files, 'rows' => $total];
        } finally {
            foreach ($streams as $s) {
                if (is_resource($s['fp'] ?? null)) {
                    fclose($s['fp']);
                }
            }
            FileHelper::removeDirectory($work);
        }
    }

    private function batches(?array $window): \Generator
    {
        if ($window === null) {
            $last = 0;
            while (true) {
                $batch = EntityRepository::filteredQuery([])->with(['people', 'agent'])
                    ->andWhere(['>', 'e.id', $last])->orderBy(['e.id' => SORT_ASC])->limit(self::CHUNK)->all();
                if (!$batch) {
                    return;
                }
                $last = (int) end($batch)['id'];
                yield $batch;
            }
        }
        [$from, $to] = $window;
        $ids = EntityRepository::filteredQuery([])->select('e.id')
            ->andWhere(['>=', 'e.publish_at', $from->format('Y-m-d H:i:s')])
            ->andWhere(['<', 'e.publish_at', $to->format('Y-m-d H:i:s')])
            ->orderBy(['e.id' => SORT_ASC])->column();
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            yield EntityRepository::filteredQuery([])->with(['people', 'agent'])->andWhere(['e.id' => $chunk])->orderBy(['e.id' => SORT_ASC])->all();
        }
    }

    private function prune(string $kind, string $before): int
    {
        $catalog = new BulkCatalog($this->db);
        $old = $catalog->expired($kind, $before);
        foreach ($old as $f) {
            $this->store->delete($f['file_key']);
        }
        $catalog->forget(array_column($old, 'id'));
        return count($old);
    }

    private function daysBefore(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date, new \DateTimeZone(self::UTC)))->modify("-$days days")->format('Y-m-d');
    }

    private function open(string $work, string $stem, string $scope, string $suffix): array
    {
        $name = $stem . strtolower($scope) . '-' . $suffix . '.csv.gz';
        $path = $work . '/' . $name;
        $fp = fopen('compress.zlib://' . $path, 'wb');
        if ($fp === false) {
            throw new \RuntimeException("Could not open $path for writing.");
        }
        fwrite($fp, self::BOM);
        fputcsv($fp, CompanyCsv::COLUMNS, ',', '"', '');
        return ['fp' => $fp, 'path' => $path, 'name' => $name];
    }
}
