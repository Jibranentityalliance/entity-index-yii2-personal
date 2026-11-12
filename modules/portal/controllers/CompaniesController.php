<?php

namespace app\modules\portal\controllers;

use app\services\EntityRepository;
use app\services\ExportUsage;
use Yii;

/** Every company, newest filing first; keywords, state, type and a filing date range narrow it down. */
class CompaniesController extends PortalController
{
    public function actionIndex()
    {
        $req = Yii::$app->request;
        $params = $this->companyFilters($req->get()) + ['page' => max(1, (int) $req->get('page', 1)), 'pageSize' => 50];
        $user = Yii::$app->user->identity;
        return $this->render('index', [
            'params' => $params,
            'res' => EntityRepository::search($params),
            'export' => $user->hasExportAccess() ? ExportUsage::summary($user) : null,
        ]);
    }
}
