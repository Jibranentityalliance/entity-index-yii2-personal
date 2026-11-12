<?php

namespace app\modules\portal\controllers;

use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

/** Base for every logged-in portal page: requires an authenticated user. */
abstract class PortalController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['logout' => ['post']]],
        ];
    }

    protected function companyFilters(array $source): array
    {
        $one = fn(string $k) => isset($source[$k]) && !is_array($source[$k]) && trim($source[$k]) !== '' ? trim($source[$k]) : null;
        return [
            'q' => $one('q'), 'state' => $one('state'), 'type' => $one('type'), 'status' => $one('status'),
            'county' => $one('county'), 'from' => $one('from'), 'to' => $one('to'),
        ];
    }
}
