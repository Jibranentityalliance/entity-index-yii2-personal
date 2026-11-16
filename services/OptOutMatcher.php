<?php

namespace app\services;

use app\helpers\Fmt;
use app\helpers\States;
use yii\db\Query;

class OptOutMatcher
{
    public static function candidates(string $ref, int $limit = 5): array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return [];
        }
        $query = (new Query())
            ->select(['e.id', 'e.state', 'e.state_filing_number', 'e.name', 'e.entity_type', 'e.publish_at', 'e.people_hidden',
                'people_count' => '(SELECT COUNT(*) FROM people p WHERE p.entity_id = e.id)'])
            ->from(['e' => 'entities'])
            ->orderBy(['e.id' => SORT_DESC])
            ->limit($limit);

        if (preg_match('~/company/([a-z0-9%-]+)/([a-z0-9-]+)~i', $ref, $m)) {
            $state = States::resolve($m[1]);
            return $state === null ? [] : $query->where(['e.state' => $state['code'], 'e.url_key' => Fmt::urlKeyFromSlug($m[2])])->all();
        }

        $state = null;
        $keys = [];
        foreach (preg_split('/\s+/', $ref) as $token) {
            $clean = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $token));
            if (strlen($clean) === 2 && ctype_alpha($clean) && States::byCode($clean)) {
                $state ??= strtoupper($clean);
            } elseif (strlen($clean) >= 4) {
                $keys[] = $clean;
            }
        }
        $whole = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $ref));
        if (strlen($whole) >= 4) {
            $keys[] = $whole;
        }
        $keys = array_values(array_unique($keys));
        if (!$keys) {
            return [];
        }
        $query->where(['e.url_key' => $keys]);
        if ($state !== null) {
            $query->andWhere(['e.state' => $state]);
        }
        return $query->all();
    }
}
