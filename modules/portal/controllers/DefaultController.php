<?php

namespace app\modules\portal\controllers;

use app\models\ContactMessage;
use app\models\OptOutRequest;
use app\models\Waitlist;
use app\services\EntityRepository;
use Yii;

class DefaultController extends PortalController
{
    public function actionIndex()
    {
        /** @var \app\models\User $user */
        $user = Yii::$app->user->identity;
        $day = EntityRepository::latestFilingDay();
        $stats = EntityRepository::stateStats();
        $latest = $day ? EntityRepository::listByDay($day, null, 1, 10) : null;
        return $this->render('index', [
            'user' => $user,
            'latestDay' => $day,
            'latest' => $latest,
            'stats' => $stats,
            'total' => EntityRepository::totalCount(),
            'week' => array_sum(array_column($stats, 'last7Days')),
            'adminStats' => $user->isAdmin() ? [
                'waitlist' => (int) Waitlist::find()->count(),
                'optOutPending' => (int) OptOutRequest::find()->where(['status' => 'pending'])->count(),
                'contactNew' => (int) ContactMessage::find()->where(['status' => 'new'])->count(),
            ] : null,
        ]);
    }
}
