<?php

namespace app\modules\portal\controllers;

use app\helpers\States;
use app\services\EntityRepository;
use Yii;

/** Coverage and freshness per jurisdiction, filterable by name, source, median lag, filing dates and status. */
class StatesController extends PortalController
{
    public function actionIndex()
    {
        $req = Yii::$app->request;
        $one = fn(string $k) => ($v = $req->get($k)) !== null && !is_array($v) && trim($v) !== '' ? trim($v) : null;
        $day = fn(?string $d) => $d !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $d : null;
        $params = ['q' => $one('q'), 'source' => $one('source'), 'lag' => $one('lag'), 'from' => $day($one('from')), 'to' => $day($one('to')), 'status' => $one('status')];
        $stats = EntityRepository::stateStats();
        $counts = $params['from'] || $params['to'] ? EntityRepository::countsByState($params['from'], $params['to']) : null;

        $lag = States::LAGS[$params['lag'] ?? ''] ?? null;
        $q = $params['q'] !== null ? strtolower($params['q']) : null;
        $states = array_filter(States::all(), function (array $st) use ($params, $stats, $lag, $q) {
            $s = $stats[$st['code']] ?? null;
            $hours = $s['medianLagHours'] ?? null;
            return ($q === null || str_contains(strtolower($st['name']), $q) || strtolower($st['code']) === $q)
                && ($params['source'] === null || States::source($st) === $params['source'])
                && ($params['status'] === null || States::health($s) === $params['status'])
                && ($lag === null || ($hours !== null && $hours > $lag[1] && $hours <= $lag[2]));
        });
        return $this->render('index', compact('params', 'stats', 'counts', 'states'));
    }
}
