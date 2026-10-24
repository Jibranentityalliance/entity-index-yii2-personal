<?php

namespace app\modules\portal\controllers;

use app\services\EntityRepository;

class DefaultController extends PortalController
{
    public function actionIndex()
    {
        return $this->render('index', [
            'stats' => EntityRepository::stateStats(),
            'week' => EntityRepository::weekOverview(),
        ]);
    }
}
