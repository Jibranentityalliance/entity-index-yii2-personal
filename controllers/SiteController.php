<?php

namespace app\controllers;

use app\models\ContactMessage;
use app\services\MirrorStats;
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
        // Numbers come from site_stats, refreshed by `php yii stats/refresh` (cron); null until its first run.
        return $this->render('index', ['stats' => MirrorStats::load(Yii::$app->db)]);
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
        $s = MirrorStats::load(Yii::$app->db) ?? [];
        return $this->render('coming-soon', [
            'today' => $s['last_24h'] ?? 0,
            'jurisdictions' => $s['states_30d'] ?? 0,
        ]);
    }
}
