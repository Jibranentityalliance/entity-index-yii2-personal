<?php

namespace app\modules\portal\controllers;

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
        return $this->render('index', [
            'user' => $user,
            'latestDay' => $day,
            'latestCount' => $day ? EntityRepository::listByDay($day, null, 1, 1)['total'] : 0,
            'total' => EntityRepository::totalCount(),
            'adminStats' => $user->isAdmin() ? [
                'waitlist' => (int) Waitlist::find()->count(),
                'optOutPending' => (int) OptOutRequest::find()->where(['status' => 'pending'])->count(),
            ] : null,
        ]);
    }
}
