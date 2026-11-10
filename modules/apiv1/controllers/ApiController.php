<?php

namespace app\modules\apiv1\controllers;

use app\models\ApiKey;
use app\modules\apiv1\filters\ApiKeyAuth;
use app\modules\apiv1\filters\ApiQuota;
use app\services\ApiUsage;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\Response;

abstract class ApiController extends Controller
{
    public ?ApiKey $apiKey = null;

    public function behaviors()
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => ['application/json' => Response::FORMAT_JSON],
            ],
            'authenticator' => ['class' => ApiKeyAuth::class],
            'quota' => ['class' => ApiQuota::class],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['*' => ['GET', 'HEAD']]],
        ];
    }

    protected function recordRows(int $rows): void
    {
        $r = ApiUsage::addRows(Yii::$app->user->identity, $rows);
        if ($r['limit'] !== null) {
            Yii::$app->response->getHeaders()->set('X-Rows-Remaining', (string) max(0, $r['limit'] - $r['used']));
        }
    }

    public function verbs()
    {
        return [];
    }
}
