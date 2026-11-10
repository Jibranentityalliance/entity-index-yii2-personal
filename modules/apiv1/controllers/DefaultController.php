<?php

namespace app\modules\apiv1\controllers;

use yii\filters\ContentNegotiator;
use yii\rest\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class DefaultController extends Controller
{
    public function behaviors()
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => ['application/json' => Response::FORMAT_JSON],
            ],
        ];
    }

    public function verbs()
    {
        return [];
    }

    public function actionIndex(): array
    {
        return ['name' => 'EntityIndex API', 'version' => 'v1'];
    }

    public function actionNotFound()
    {
        throw new NotFoundHttpException('Unknown API endpoint.');
    }
}
