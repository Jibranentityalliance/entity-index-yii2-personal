<?php

namespace app\controllers;

use app\models\ContactMessage;
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
        $stats = EntityRepository::stateStats();
        $latestDay = EntityRepository::latestFilingDay();
        $lags = array_values(array_filter(array_column($stats, 'medianLagHours'), fn($x) => $x !== null));
        sort($lags);
        return $this->render('index', [
            'latestDay' => $latestDay,
            'latestCount' => $latestDay ? EntityRepository::listByDay($latestDay, null, 1, 1)['total'] : 0,
            'jurisdictions' => count($stats),
            'total' => EntityRepository::totalCount(),
            'medianLag' => $lags ? $lags[intdiv(count($lags), 2)] : null,
            'week' => array_sum(array_column($stats, 'last7Days')),
        ]);
    }

    public function actionFeatures()
    {
        return $this->render('features');
    }

    public function actionFaq()
    {
        return $this->render('faq', ['faq' => require Yii::getAlias('@app/config/faq.php')]);
    }

    public function actionAbout()
    {
        return $this->render('about');
    }

    public function actionContact()
    {
        $topic = Yii::$app->request->get('topic');
        return $this->render('contact', ['topic' => is_string($topic) && isset(ContactMessage::TOPICS[$topic]) ? $topic : 'general']);
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
