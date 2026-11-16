<?php

namespace app\commands;

use app\services\bulk\Bulk;
use app\services\bulk\BulkBuilder;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Builds the daily bulk files for Data license accounts: one gzipped CSV with every published company and one per state.
 *
 *   php yii bulk/build
 *
 * Meant for cron once a day; a run that finds another one still going exits right away. See docs/DEPLOY.md.
 */
class BulkController extends Controller
{
    public function actionBuild(): int
    {
        $mutex = Yii::$app->mutex;
        if (!$mutex->acquire('bulk-build')) {
            $this->stdout("Another bulk/build run is still going; skipping.\n");
            return ExitCode::OK;
        }
        try {
            $config = Bulk::config();
            $start = microtime(true);
            $builder = new BulkBuilder(Yii::$app->db, Bulk::store(), Yii::getAlias('@runtime/bulk-tmp'), (int) $config['keepDays']);
            $r = $builder->build(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), function (int $n) use ($start) {
                if ($n % 100000 < 1000) {
                    $this->stdout(sprintf("%s %s rows, %.0f rows/s\n", gmdate('H:i:s'), number_format($n), $n / max(1, microtime(true) - $start)));
                }
            });
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->stderr(gmdate('Y-m-d H:i:s') . ' bulk/build failed: ' . get_class($e) . ': ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            $mutex->release('bulk-build');
        }
        if ($r['rows'] === 0) {
            $this->stdout(gmdate('Y-m-d H:i:s') . " nothing is published yet; no files built.\n");
            return ExitCode::OK;
        }
        $bytes = array_sum(array_column($r['files'], 'bytes'));
        $this->stdout(sprintf("%s built %s: %s companies, %d files, %.1f MB, %d old files removed, %.0f s\n",
            gmdate('Y-m-d H:i:s'), $r['date'], number_format($r['rows']), count($r['files']), $bytes / 1048576, $r['pruned'] ?? 0, microtime(true) - $start), Console::FG_GREEN);
        return ExitCode::OK;
    }
}
