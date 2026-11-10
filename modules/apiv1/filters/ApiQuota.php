<?php

namespace app\modules\apiv1\filters;

use app\services\ApiUsage;
use Yii;
use yii\base\ActionFilter;

class ApiQuota extends ActionFilter
{
    public function beforeAction($action)
    {
        $user = Yii::$app->user->identity;
        if ($user === null) {
            return true;
        }
        $r = ApiUsage::consume($user);
        $response = Yii::$app->response;
        $h = $response->getHeaders();
        if ($r['minute']['limit'] !== null) {
            $h->set('X-RateLimit-Limit', (string) $r['minute']['limit']);
            $h->set('X-RateLimit-Remaining', (string) max(0, $r['minute']['limit'] - $r['minute']['used']));
            $h->set('X-RateLimit-Reset', (string) $r['minute']['resetsAt']);
        }
        if ($r['month']['limit'] !== null) {
            $h->set('X-Quota-Limit', (string) $r['month']['limit']);
            $h->set('X-Quota-Remaining', (string) max(0, $r['month']['limit'] - $r['month']['used']));
            $h->set('X-Quota-Reset', (string) $r['month']['resetsAt']);
        }
        if ($r['rows']['limit'] !== null) {
            $h->set('X-Rows-Limit', (string) $r['rows']['limit']);
            $h->set('X-Rows-Remaining', (string) max(0, $r['rows']['limit'] - $r['rows']['used']));
            $h->set('X-Rows-Reset', (string) $r['rows']['resetsAt']);
        }
        if ($r['ok']) {
            return true;
        }
        $h->set('Retry-After', (string) $r['retryAfter']);
        $response->setStatusCode(429);
        $response->data = [
            'name' => 'Too Many Requests',
            'message' => match ($r['scope']) {
                'minute' => 'Rate limit exceeded. Try again in ' . $r['retryAfter'] . ' seconds.',
                'rows' => 'Monthly row quota used up. It resets on ' . gmdate('Y-m-d', $r['rows']['resetsAt']) . ' UTC.',
                default => 'Monthly API quota used up. It resets on ' . gmdate('Y-m-d', $r['month']['resetsAt']) . ' UTC.',
            },
            'code' => 0,
            'status' => 429,
        ];
        return false;
    }
}
