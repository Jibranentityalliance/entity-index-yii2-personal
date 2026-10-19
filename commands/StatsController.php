<?php

namespace app\commands;

use app\services\MirrorStats;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Refreshes the home-page numbers from the replicated `manual_companies` table (MIRROR_DB, default ein_skip_tracing).
 *
 *   php yii stats/refresh
 *
 * Meant for cron every 5 minutes; a run that finds another one still going exits right away. See docs/DEPLOY.md.
 */
class StatsController extends Controller
{
    public function actionRefresh(): int
    {
        $mutex = Yii::$app->mutex;
        if (!$mutex->acquire('stats-refresh')) {
            $this->stdout("Another stats/refresh run is still going; skipping.\n");
            return ExitCode::OK;
        }
        try {
            $p = Yii::$app->params;
            $s = (new MirrorStats(Yii::$app->db, $p['mirrorDb'], new \DateTimeZone($p['mirrorTz'])))->refresh();
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->stderr(gmdate('Y-m-d H:i:s') . ' stats/refresh failed: ' . get_class($e) . ': ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            $mutex->release('stats-refresh');
        }
        $this->stdout(sprintf("%s total %d, last 24h %d, last 7 days %d, states (30 days) %d, median lag %s days, newest %s\n",
            gmdate('Y-m-d H:i:s'), $s['total'], $s['last_24h'], $s['last_7d'], $s['states_30d'], $s['median_lag_days'] ?? '-', $s['newest_at'] ?? '-'),
            Console::FG_GREEN);
        return ExitCode::OK;
    }
}
