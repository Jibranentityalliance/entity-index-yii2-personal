<?php

namespace app\models;

use app\helpers\States;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class SavedSearch extends ActiveRecord
{
    public const FILTERS = ['q', 'state', 'type', 'status', 'county'];

    public static function tableName()
    {
        return 'saved_search';
    }

    public static function forUser(int $userId): ActiveQuery
    {
        return static::find()->where(['user_id' => $userId])->orderBy(['id' => SORT_ASC]);
    }

    public static function cleanFilters(array $source): array
    {
        $out = [];
        foreach (self::FILTERS as $k) {
            $v = $source[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $out[$k] = trim($v);
            }
        }
        if (isset($out['state'])) {
            $out['state'] = strtoupper($out['state']);
        }
        ksort($out);
        return $out;
    }

    public static function hashFilters(array $filters): string
    {
        return hash('sha256', json_encode($filters, JSON_UNESCAPED_UNICODE));
    }

    public static function describe(array $filters): string
    {
        $state = isset($filters['state']) ? (States::name($filters['state']) ?: $filters['state']) : null;
        $where = isset($filters['county']) ? $filters['county'] . ' County' . ($state ? ', ' . $state : '') : $state;
        $parts = array_filter([
            isset($filters['q']) ? '“' . $filters['q'] . '”' : null,
            $filters['status'] ?? null,
            $filters['type'] ?? 'companies',
            $where ? 'in ' . $where : null,
        ]);
        $text = implode(' ', $parts);
        return $filters ? 'New ' . $text : 'All new companies';
    }

    public function rules()
    {
        return [
            ['name', 'trim'],
            ['name', 'required'],
            ['name', 'string', 'max' => 100],
        ];
    }

    public function filters(): array
    {
        return json_decode((string) $this->params, true) ?: [];
    }

    public function setFilters(array $filters): void
    {
        $filters = self::cleanFilters($filters);
        $this->params = json_encode($filters, JSON_UNESCAPED_UNICODE);
        $this->params_hash = self::hashFilters($filters);
    }

    public function summary(): string
    {
        $f = $this->filters();
        $rows = array_filter([
            'Keywords' => $f['q'] ?? null,
            'State' => isset($f['state']) ? (States::name($f['state']) ?: $f['state']) : null,
            'County' => $f['county'] ?? null,
            'Type' => $f['type'] ?? null,
            'Status' => $f['status'] ?? null,
        ]);
        if (!$rows) {
            return 'Every new company';
        }
        return implode('; ', array_map(fn($k, $v) => "$k: $v", array_keys($rows), $rows));
    }

    public function beforeSave($insert)
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $now;
            $this->cursor_at = $this->cursor_at ?: $now;
            $this->unsubscribe_token = $this->unsubscribe_token ?: bin2hex(random_bytes(16));
        }
        $this->updated_at = $now;
        return parent::beforeSave($insert);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
