<?php

namespace app\modules\portal\controllers;

use app\helpers\Fmt;
use app\models\User;
use app\services\ApiUsage;
use app\services\EntityRepository;
use app\services\ExportUsage;
use app\services\FreePlan;
use Yii;

class DefaultController extends PortalController
{
    public function actionIndex()
    {
        return $this->render('index', [
            'stats' => EntityRepository::stateStats(),
            'week' => EntityRepository::weekOverview(),
            'limits' => $this->limits(Yii::$app->user->identity),
        ]);
    }

    private function limits(User $user): array
    {
        $lines = [];
        if ($user->isFree()) {
            $f = FreePlan::summary($user);
            $lines[] = ['Lookups (daily)', $this->ratio($f['used'], $f['lookupsPerDay'])];
        }
        if ($user->hasApiAccess()) {
            $a = ApiUsage::monthSummary($user);
            $lines[] = ['API (monthly)', $this->ratio($a['used'], $a['limit'])];
        }
        if ($user->hasExportAccess()) {
            $x = ExportUsage::summary($user);
            $lines[] = ['CSV export (monthly)', $this->ratio($x['used'], $x['limit'])];
        }
        return $lines;
    }

    private function ratio(int $used, ?int $limit): string
    {
        return $limit === null ? 'Unlimited' : Fmt::num($used) . '/' . Fmt::num($limit);
    }
}
