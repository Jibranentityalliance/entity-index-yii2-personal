<?php

namespace app\services\bulk;

use app\helpers\States;
use yii\db\Connection;
use yii\db\Query;

class BulkCatalog
{
    public const ALL = 'ALL';

    public function __construct(private Connection $db)
    {
    }

    public function dates(): array
    {
        return (new Query())->select('build_date')->distinct()->from('bulk_file')->orderBy(['build_date' => SORT_DESC])->column($this->db);
    }

    public function files(string $date): array
    {
        $rows = (new Query())->from('bulk_file')->where(['build_date' => $date])->all($this->db);
        usort($rows, function (array $a, array $b): int {
            if ($a['scope'] === self::ALL || $b['scope'] === self::ALL) {
                return ($b['scope'] === self::ALL) <=> ($a['scope'] === self::ALL);
            }
            return strcmp(self::label($a['scope']), self::label($b['scope']));
        });
        return $rows;
    }

    public function file(int $id): ?array
    {
        return (new Query())->from('bulk_file')->where(['id' => $id])->one($this->db) ?: null;
    }

    public function replace(string $date, array $files): void
    {
        $tx = $this->db->beginTransaction();
        try {
            $this->db->createCommand()->delete('bulk_file', ['build_date' => $date])->execute();
            if ($files) {
                $this->db->createCommand()->batchInsert('bulk_file',
                    ['build_date', 'scope', 'file_key', 'file_name', 'row_count', 'bytes', 'sha256', 'created_at'],
                    array_map(fn(array $f) => [$date, $f['scope'], $f['key'], $f['name'], $f['rows'], $f['bytes'], $f['sha256'], gmdate('Y-m-d H:i:s')], $files))->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    public function expired(string $beforeDate): array
    {
        return (new Query())->select(['id', 'file_key'])->from('bulk_file')->where(['<', 'build_date', $beforeDate])->all($this->db);
    }

    public function forget(array $ids): void
    {
        if ($ids) {
            $this->db->createCommand()->delete('bulk_file', ['id' => $ids])->execute();
        }
    }

    public function downloadsToday(int $userId): int
    {
        return (int) (new Query())->from('bulk_download')
            ->where(['and', ['user_id' => $userId], ['>=', 'created_at', gmdate('Y-m-d') . ' 00:00:00']])->count('*', $this->db);
    }

    public function logDownload(int $userId, array $file): void
    {
        $this->db->createCommand()->insert('bulk_download', [
            'user_id' => $userId, 'file_id' => $file['id'], 'file_name' => $file['file_name'], 'created_at' => gmdate('Y-m-d H:i:s'),
        ])->execute();
    }

    public static function label(string $scope): string
    {
        return match ($scope) {
            self::ALL => 'All states',
            'NA' => 'Unknown state',
            default => States::name($scope) ?: $scope,
        };
    }
}
