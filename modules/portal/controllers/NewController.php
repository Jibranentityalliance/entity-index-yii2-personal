<?php

namespace app\modules\portal\controllers;

use app\helpers\Fmt;
use app\helpers\States;
use app\services\EntityRepository;
use Yii;
use yii\web\NotFoundHttpException;

class NewController extends PortalController
{
    public function actionIndex()
    {
        [$latest, $days, $day] = $this->days();
        $res = EntityRepository::listByDay($day, null, max(1, (int) Yii::$app->request->get('page', 1)), 50);
        return $this->render('index', compact('day', 'days', 'res'));
    }

    public function actionState(string $state)
    {
        $st = States::resolve($state);
        if (!$st) {
            throw new NotFoundHttpException('Unknown jurisdiction.');
        }
        if ($state !== strtolower($st['code'])) {
            return $this->redirect(Fmt::stateUrl($st['code']), 308);
        }
        [$latest, $days, $day] = $this->days();
        $res = EntityRepository::listByDay($day, $st['code'], max(1, (int) Yii::$app->request->get('page', 1)), 50);
        return $this->render('state', ['st' => $st, 'day' => $day, 'days' => $days, 'res' => $res, 'stats' => EntityRepository::stateStats()[$st['code']] ?? null]);
    }

    /** @return array{0:string,1:string[],2:string} latest filing day, selectable weekdays, selected day */
    private function days(): array
    {
        $latest = EntityRepository::latestFilingDay() ?? gmdate('Y-m-d');
        $d = Yii::$app->request->get('day');
        $day = is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : $latest;
        return [$latest, Fmt::recentFilingDays($latest), $day];
    }
}
