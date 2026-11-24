<?php

namespace app\services;

use app\models\Entity;
use app\models\WebhookDelivery;
use app\models\WebhookEndpoint;
use app\models\WebhookEvent;
use app\modules\apiv1\serializers\EntitySerializer;
use Yii;
use yii\db\Query;

class Webhooks
{
    public const USER_AGENT = 'EntityIndex-Webhooks/1';
    private const SCAN_PER_RUN = 10000;
    private const SCAN_CHUNK = 1000;

    private static function cfg(string $key)
    {
        return Yii::$app->params['webhooks'][$key];
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function detect(): int
    {
        $now = self::now();
        $since = gmdate('Y-m-d H:i:s', time() - (int) self::cfg('eventWindowHours') * 3600);
        return Yii::$app->db->createCommand(
            'INSERT IGNORE INTO webhook_event (entity_id, published_at, created_at)
             SELECT e.id, e.publish_at, :now FROM entities e
             WHERE e.publish_at > :since AND e.publish_at <= :now
               AND NOT EXISTS (SELECT 1 FROM webhook_event w WHERE w.entity_id = e.id)
             ORDER BY e.publish_at, e.id',
            [':now' => $now, ':since' => $since]
        )->execute();
    }

    public static function enqueue(): int
    {
        $created = 0;
        foreach (WebhookEndpoint::find()->where(['status' => WebhookEndpoint::STATUS_ACTIVE])->each() as $endpoint) {
            $created += self::enqueueFor($endpoint);
        }
        return $created;
    }

    public static function enqueueFor(WebhookEndpoint $endpoint): int
    {
        $created = 0;
        $scanned = 0;
        $batch = (int) self::cfg('batchSize');
        while ($scanned < self::SCAN_PER_RUN) {
            $events = (new Query())->select(['id', 'entity_id'])->from('webhook_event')
                ->where(['>', 'id', $endpoint->cursor_event_id])->orderBy(['id' => SORT_ASC])->limit(self::SCAN_CHUNK)->all();
            if (!$events) {
                break;
            }
            $scanned += count($events);
            $q = (new Query())->select('e.id')->from(['e' => 'entities'])
                ->where(['e.id' => array_column($events, 'entity_id')])
                ->andWhere(EntityRepository::published('e'));
            if ($endpoint->stateList) {
                $q->andWhere(['e.state' => $endpoint->stateList]);
            }
            if ($endpoint->typeList) {
                $q->andWhere(['e.entity_type' => $endpoint->typeList]);
            }
            $matched = array_flip(array_map('intval', $q->column()));
            $ids = [];
            foreach ($events as $ev) {
                if (isset($matched[(int) $ev['entity_id']])) {
                    $ids[] = (int) $ev['entity_id'];
                }
            }
            $tx = Yii::$app->db->beginTransaction();
            foreach (array_chunk($ids, $batch) as $chunk) {
                self::queue($endpoint, WebhookDelivery::EVENT_PUBLISHED, $chunk);
                $created++;
            }
            $endpoint->cursor_event_id = (int) end($events)['id'];
            $endpoint->updateAttributes(['cursor_event_id']);
            $tx->commit();
        }
        return $created;
    }

    public static function queue(WebhookEndpoint $endpoint, string $event, array $entityIds): WebhookDelivery
    {
        $d = new WebhookDelivery([
            'endpoint_id' => $endpoint->id,
            'event' => $event,
            'entity_ids' => json_encode(array_values($entityIds)),
            'entity_count' => count($entityIds),
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt_at' => self::now(),
            'created_at' => self::now(),
        ]);
        $d->save(false);
        return $d;
    }

    public static function deliverDue(int $limit = 200): array
    {
        $sent = $failed = 0;
        $due = WebhookDelivery::find()->alias('d')->innerJoinWith('endpoint ep', false)
            ->where(['d.status' => 'pending', 'ep.status' => WebhookEndpoint::STATUS_ACTIVE])
            ->andWhere(['<=', 'd.next_attempt_at', self::now()])
            ->orderBy(['d.next_attempt_at' => SORT_ASC, 'd.id' => SORT_ASC])->limit($limit)->all();
        foreach ($due as $d) {
            self::attempt($d) ? $sent++ : $failed++;
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    public static function attempt(WebhookDelivery $d): bool
    {
        $endpoint = $d->endpoint;
        $now = self::now();
        $payload = self::payload($d);
        if ($payload === null) {
            $d->setAttributes(['status' => 'success', 'delivered_at' => $now, 'last_attempt_at' => $now, 'error' => 'Nothing to send: the companies are no longer public.', 'next_attempt_at' => null], false);
            $d->save(false);
            return true;
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $result = self::post($endpoint, $d, $body);

        $d->attempts++;
        $d->last_attempt_at = $now;
        $d->response_code = $result['code'] ?: null;
        $d->response_body = $result['body'] !== '' ? mb_substr($result['body'], 0, 500) : null;
        $d->error = $result['error'];
        $ok = $result['code'] >= 200 && $result['code'] < 300;
        if ($ok) {
            $d->status = 'success';
            $d->delivered_at = $now;
            $d->next_attempt_at = null;
            $endpoint->failure_count = 0;
            $endpoint->last_success_at = $now;
        } else {
            $schedule = self::cfg('retryAfter');
            if ($d->event === WebhookDelivery::EVENT_TEST || $d->attempts > count($schedule)) {
                $d->status = 'failed';
                $d->next_attempt_at = null;
            } else {
                $d->next_attempt_at = gmdate('Y-m-d H:i:s', time() + (int) $schedule[$d->attempts - 1]);
            }
            $endpoint->failure_count++;
            $endpoint->last_failure_at = $now;
            if ($endpoint->failure_count >= (int) self::cfg('disableAfterFailures') && $endpoint->status === WebhookEndpoint::STATUS_ACTIVE) {
                $endpoint->status = WebhookEndpoint::STATUS_DISABLED;
                $endpoint->disabled_reason = 'Turned off after ' . $endpoint->failure_count . ' failed attempts in a row on ' . $now . ' UTC.';
            }
        }
        $d->save(false);
        $endpoint->updateAttributes(['failure_count', 'last_success_at', 'last_failure_at', 'status', 'disabled_reason']);
        return $ok;
    }

    public static function payload(WebhookDelivery $d): ?array
    {
        if ($d->event === WebhookDelivery::EVENT_TEST) {
            $data = [self::sampleEntity()];
        } else {
            $rows = Entity::find()->alias('e')->asArray()->where(['e.id' => $d->entityIds()])
                ->andWhere(EntityRepository::published('e'))->with(['people', 'agent'])->orderBy(['e.id' => SORT_ASC])->all();
            if (!$rows) {
                return null;
            }
            $data = array_map(fn(array $e) => EntitySerializer::entity($e), $rows);
        }
        return [
            'id' => 'dlv_' . $d->id,
            'event' => $d->event,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime($d->created_at . ' UTC')),
            'count' => count($data),
            'data' => $data,
        ];
    }

    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    private static function post(WebhookEndpoint $endpoint, WebhookDelivery $d, string $body): array
    {
        $parts = parse_url($endpoint->url);
        $host = (string) ($parts['host'] ?? '');
        $scheme = $parts['scheme'] ?? '';
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $allowPrivate = (bool) self::cfg('allowPrivate');
        if ($scheme !== 'https' && !$allowPrivate) {
            return ['code' => 0, 'body' => '', 'error' => 'Only https:// URLs are allowed.'];
        }
        $literal = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false;
        $ip = $literal ? trim($host, '[]') : (gethostbynamel($host)[0] ?? null);
        if ($ip === null) {
            return ['code' => 0, 'body' => '', 'error' => 'Could not resolve ' . $host . '.'];
        }
        if (!$allowPrivate && !WebhookEndpoint::isPublicIp($ip)) {
            return ['code' => 0, 'body' => '', 'error' => $host . ' resolves to a private address.'];
        }

        $ts = time();
        $ch = curl_init($endpoint->url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => (int) self::cfg('timeout'),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | ($allowPrivate ? CURLPROTO_HTTP : 0),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: ' . self::USER_AGENT,
                'X-EntityIndex-Event: ' . $d->event,
                'X-EntityIndex-Delivery: dlv_' . $d->id,
                'X-EntityIndex-Signature: ' . self::signature($endpoint->secret, $ts, $body),
            ],
        ]);
        if (!$literal) {
            curl_setopt($ch, CURLOPT_RESOLVE, [$host . ':' . $port . ':' . $ip]);
        }
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $response === false ? curl_error($ch) : null;
        curl_close($ch);
        if ($error === null && ($code < 200 || $code >= 300)) {
            $error = $code >= 300 && $code < 400 ? 'Redirects are not followed; use the final URL.' : 'The endpoint answered HTTP ' . $code . '.';
        }
        return ['code' => $code, 'body' => is_string($response) ? $response : '', 'error' => $error === null ? null : mb_substr($error, 0, 255)];
    }

    public static function sendTest(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $d = self::queue($endpoint, WebhookDelivery::EVENT_TEST, []);
        self::attempt($d);
        return $d;
    }

    public static function redeliver(WebhookDelivery $d): void
    {
        $d->setAttributes(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => self::now(), 'error' => null], false);
        $d->save(false);
    }

    public static function prune(): int
    {
        $cut = gmdate('Y-m-d H:i:s', time() - (int) self::cfg('keepDays') * 86400);
        $db = Yii::$app->db;
        $n = $db->createCommand()->delete('webhook_delivery', ['and', ['<>', 'status', 'pending'], ['<', 'created_at', $cut]])->execute();
        $minCursor = (int) (new Query())->from('webhook_endpoint')->min('cursor_event_id');
        $n += $db->createCommand()->delete('webhook_event', ['and', ['<', 'created_at', $cut], ['<=', 'id', $minCursor]])->execute();
        return $n;
    }

    public static function sampleEntity(): array
    {
        return [
            'id' => 0,
            'state' => 'FL',
            'filing_number' => 'L26000123456',
            'name' => 'Example Roofing LLC',
            'entity_type' => 'LLC',
            'status' => 'Active',
            'filed_at' => gmdate('Y-m-d\T00:00:00\Z'),
            'effective_at' => gmdate('Y-m-d\T00:00:00\Z'),
            'indexed_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'county' => 'Broward',
            'principal_address' => '100 Example Ave, Fort Lauderdale, FL 33301',
            'mailing_address' => '100 Example Ave, Fort Lauderdale, FL 33301',
            'industry' => ['naics' => '238160', 'label' => 'Roofing Contractors', 'estimated' => true],
            'registered_agent' => ['name' => 'Example Agent Inc', 'commercial' => true, 'address' => ['full' => '200 Agent St, Tallahassee, FL 32301', 'street' => '200 Agent St', 'city' => 'Tallahassee', 'state' => 'FL', 'zip' => '32301'], 'phone' => null],
            'people' => [['name' => 'Jane Example', 'role' => 'officer', 'title' => 'MGR', 'address' => ['full' => '100 Example Ave, Fort Lauderdale, FL 33301', 'street' => '100 Example Ave', 'city' => 'Fort Lauderdale', 'state' => 'FL', 'zip' => '33301'], 'phone' => null]],
            'source' => ['url' => null, 'pulled_at' => gmdate('Y-m-d\TH:i:s\Z')],
        ];
    }
}
