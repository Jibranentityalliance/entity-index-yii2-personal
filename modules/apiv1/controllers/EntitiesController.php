<?php

namespace app\modules\apiv1\controllers;

use app\models\Entity;
use app\modules\apiv1\serializers\EntitySerializer;
use app\services\ApiUsage;
use app\services\EntityRepository;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class EntitiesController extends ApiController
{
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 1000;

    public function actionIndex(): array
    {
        $r = Yii::$app->request;
        $limit = $this->limit($r->get('limit'));
        $rowsLeft = ApiUsage::rowsRemaining(Yii::$app->user->identity);
        if ($rowsLeft !== null) {
            $limit = min($limit, $rowsLeft);
        }
        $desc = $this->order($r->get('order')) === 'desc';
        $params = [
            'q' => $this->text($r->get('q'), 'q'),
            'state' => $this->state($r->get('state')),
            'type' => $this->text($r->get('type'), 'type'),
            'status' => $this->text($r->get('status'), 'status'),
            'county' => $this->text($r->get('county'), 'county'),
            'from' => $this->day($r->get('filed_after'), 'filed_after'),
            'to' => $this->day($r->get('filed_before'), 'filed_before'),
        ];

        $q = EntityRepository::filteredQuery($params)->with(['people', 'agent']);
        $cursor = $this->cursor($r->get('cursor'));
        if ($cursor !== null) {
            $q->andWhere([$desc ? '<' : '>', 'e.id', $cursor]);
        }
        $rows = $q->orderBy(['e.id' => $desc ? SORT_DESC : SORT_ASC])->limit($limit + 1)->all();

        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $last = $rows ? (int) end($rows)['id'] : null;
        $this->recordRows(count($rows));

        return [
            'data' => array_map(fn(array $e) => EntitySerializer::entity($e), $rows),
            'has_more' => $hasMore,
            'next_cursor' => $hasMore ? rtrim(strtr(base64_encode((string) $last), '+/', '-_'), '=') : null,
        ];
    }

    public function actionView(int $id): array
    {
        return $this->respond(['e.id' => $id]);
    }

    public function actionFiling(string $state, string $filing): array
    {
        $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $filing));
        return $this->respond(['e.state' => strtoupper($state), 'e.url_key' => $key]);
    }

    private function respond(array $where): array
    {
        $e = Entity::find()->alias('e')->asArray()->where($where)->with(['people', 'agent', 'filings'])->orderBy(['e.id' => SORT_DESC])->one();
        if ($e === null) {
            throw new NotFoundHttpException('Company not found.');
        }
        $this->recordRows(1);
        return ['data' => EntitySerializer::entity($e, true)];
    }

    private function limit($v): int
    {
        if ($v === null || $v === '') {
            return self::DEFAULT_LIMIT;
        }
        if (!ctype_digit((string) $v) || (int) $v < 1 || (int) $v > self::MAX_LIMIT) {
            throw new BadRequestHttpException('limit must be a whole number from 1 to ' . self::MAX_LIMIT . '.');
        }
        return (int) $v;
    }

    private function order($v): string
    {
        $v = $v === null || $v === '' ? 'desc' : strtolower((string) $v);
        if (!in_array($v, ['asc', 'desc'], true)) {
            throw new BadRequestHttpException('order must be asc or desc.');
        }
        return $v;
    }

    private function cursor($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $id = is_string($v) ? base64_decode(strtr($v, '-_', '+/'), true) : false;
        if ($id === false || !ctype_digit($id)) {
            throw new BadRequestHttpException('Invalid cursor.');
        }
        return (int) $id;
    }

    private function state($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_string($v) || !preg_match('/^[A-Za-z]{2}$/', $v)) {
            throw new BadRequestHttpException('state must be a two-letter code.');
        }
        return strtoupper($v);
    }

    private function day($v, string $name): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $d = is_string($v) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $v) : false;
        if ($d === false || $d->format('Y-m-d') !== $v) {
            throw new BadRequestHttpException("$name must be a date like 2026-09-28.");
        }
        return $v;
    }

    private function text($v, string $name): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_string($v) || mb_strlen($v) > 200) {
            throw new BadRequestHttpException("$name must be text of at most 200 characters.");
        }
        return $v;
    }
}
