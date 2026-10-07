<?php

namespace app\controllers;

use app\services\EntityRepository;
use Yii;
use yii\web\Controller;

class SearchController extends Controller
{
    public function actionIndex()
    {
        $req = Yii::$app->request;
        $one = fn(string $k) => ($v = $req->get($k)) !== null && !is_array($v) && trim($v) !== '' ? trim($v) : null;
        $params = [
            'q' => $one('q'), 'state' => $one('state'), 'type' => $one('type'), 'status' => $one('status'),
            'county' => $one('county'), 'from' => $one('from'), 'to' => $one('to'),
            'page' => max(1, (int) $req->get('page', 1)), 'pageSize' => 25,
        ];
        return $this->render('index', ['params' => $params, 'res' => EntityRepository::search($params)]);
    }
}
