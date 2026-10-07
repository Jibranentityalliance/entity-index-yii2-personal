<?php

namespace app\controllers;

use app\services\EntityRepository;
use Yii;
use yii\web\Controller;
use yii\web\ErrorAction;

class SiteController extends Controller
{
    public function actions()
    {
        return ['error' => ['class' => ErrorAction::class]];
    }

    public function actionIndex()
    {
        $latestDay = EntityRepository::latestFilingDay();
        $stats = EntityRepository::stateStats();
        $latest = $latestDay ? EntityRepository::listByDay($latestDay, null, 1, 6) : null;
        $lags = array_values(array_filter(array_column($stats, 'medianLagHours'), fn($x) => $x !== null));
        sort($lags);
        return $this->render('index', [
            'latestDay' => $latestDay,
            'latest' => $latest,
            'stats' => $stats,
            'total' => EntityRepository::totalCount(),
            'medianLag' => $lags ? $lags[intdiv(count($lags), 2)] : null,
            'week' => array_sum(array_column($stats, 'last7Days')),
        ]);
    }

    public function actionComingSoon()
    {
        $this->layout = 'bare';
        $day = EntityRepository::latestFilingDay();
        return $this->render('coming-soon', [
            'day' => $day,
            'today' => $day ? EntityRepository::listByDay($day, null, 1, 1)['total'] : 0,
            'jurisdictions' => count(EntityRepository::stateStats()),
        ]);
    }
}
