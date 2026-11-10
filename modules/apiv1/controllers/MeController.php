<?php

namespace app\modules\apiv1\controllers;

use app\services\ApiUsage;
use Yii;

class MeController extends ApiController
{
    public function actionIndex(): array
    {
        $user = Yii::$app->user->identity;
        $month = ApiUsage::monthSummary($user);
        return [
            'email' => $user->email,
            'plan' => $user->plan,
            'quota' => [
                'monthly_limit' => $month['limit'],
                'used_this_month' => $month['used'],
                'monthly_row_limit' => $month['rowLimit'],
                'rows_used_this_month' => $month['rowsUsed'],
                'resets_at' => gmdate('Y-m-d\TH:i:s\Z', $month['resetsAt']),
                'rate_limit_per_minute' => ApiUsage::limitsFor($user)['perMinute'] ?? null,
            ],
            'key' => ['name' => $this->apiKey->name, 'prefix' => $this->apiKey->key_prefix, 'created_at' => $this->apiKey->created_at],
        ];
    }
}
