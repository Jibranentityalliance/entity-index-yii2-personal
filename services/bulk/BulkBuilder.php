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

    public function __construct(
        private Connection $db,
        private BulkStore $store,
        private string $tmpDir,
        private int $keepDays,
    ) {
    }

    public function build(\DateTimeImmutable $at, ?callable $progress = null): array
    {
        $date = $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
        $work = rtrim($this->tmpDir, '/') . '/' . $date . '-' . bin2hex(random_bytes(4));
        FileHelper::createDirectory($work, 0750);

        $streams = [];
        $rows = [];
        try {
            $national = $this->open($work, $date, BulkCatalog::ALL);
            $last = 0;
            $total = 0;
            while (true) {
                $batch = EntityRepository::filteredQuery([])->with(['people', 'agent'])
                    ->andWhere(['>', 'e.id', $last])->orderBy(['e.id' => SORT_ASC])->limit(self::CHUNK)->all();
                if (!$batch) {
                    break;
                }
                foreach ($batch as $e) {
                    $last = (int) $e['id'];
                    $line = CompanyCsv::line(EntitySerializer::entity($e));
                    $scope = (string) $e['state'];
                    $streams[$scope] ??= $this->open($work, $date, $scope);
                    fputcsv($streams[$scope]['fp'], $line, ',', '"', '');
                    fputcsv($national['fp'], $line, ',', '"', '');
                    $rows[$scope] = ($rows[$scope] ?? 0) + 1;
                    $total++;
                }
                if ($progress) {
                    $progress($total);
                }
            }
            if ($total === 0) {
                return ['date' => $date, 'files' => [], 'rows' => 0];
            }
            $rows[BulkCatalog::ALL] = $total;
            $streams[BulkCatalog::ALL] = $national;

            $files = [];
            foreach ($streams as $scope => $s) {
                fclose($s['fp']);
                $files[] = [
                    'scope' => $scope,
                    'key' => $date . '/' . $s['name'],
                    'name' => $s['name'],
                    'rows' => $rows[$scope],
                    'bytes' => filesize($s['path']),
                    'sha256' => hash_file('sha256', $s['path']),
                ];
                $this->store->put($date . '/' . $s['name'], $s['path']);
                $streams[$scope]['fp'] = null;
            }
            $catalog = new BulkCatalog($this->db);
            $stale = array_diff(array_column($catalog->files($date), 'file_key'), array_column($files, 'key'));
            $catalog->replace($date, $files);
            foreach ($stale as $key) {
                $this->store->delete($key);
            }
            return ['date' => $date, 'files' => $files, 'rows' => $total, 'pruned' => $this->prune($date)];
        } finally {
            foreach ($streams as $s) {
                if (is_resource($s['fp'] ?? null)) {
                    fclose($s['fp']);
                }
            }
            if (is_resource($national['fp'] ?? null)) {
                fclose($national['fp']);
            }
            FileHelper::removeDirectory($work);
        }
    }

    private function prune(string $today): int
    {
        $cut = (new \DateTimeImmutable($today, new \DateTimeZone('UTC')))->modify('-' . max(0, $this->keepDays - 1) . ' days')->format('Y-m-d');
        $catalog = new BulkCatalog($this->db);
        $old = $catalog->expired($cut);
        foreach ($old as $f) {
            $this->store->delete($f['file_key']);
        }
        $catalog->forget(array_column($old, 'id'));
        return count($old);
    }

    private function open(string $work, string $date, string $scope): array
    {
        $name = 'entityindex-' . strtolower($scope) . '-' . $date . '.csv.gz';
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
