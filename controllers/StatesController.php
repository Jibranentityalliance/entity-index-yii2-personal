<?php

namespace app\controllers;

use app\services\EntityRepository;
use yii\web\Controller;

class StatesController extends Controller
{
    public function actionIndex()
    {
        return $this->render('index', ['stats' => EntityRepository::stateStats()]);
    }
}
