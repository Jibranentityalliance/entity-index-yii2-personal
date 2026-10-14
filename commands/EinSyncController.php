<?php

namespace app\commands;

use app\services\EinSkipTracing\ManualCompanySync;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Syncs companies from the EIN skip-tracing database (DB_*_EIN_SKIP_TRACING) into ein_companies / ein_company_people.
 *
 *   php yii ein-sync/companies             next new rows from manual_companies (at most 10)
 *   php yii ein-sync/companies --limit=1   fewer rows
 *
 * The first run only records where to start (the current newest source row); later runs copy rows added after it.
 * Meant for cron, e.g. every minute; a run that finds another one still going exits right away. See docs/DEPLOY.md.
 */
class EinSyncController extends Controller
{
    /** @var int rows per run, capped at ManualCompanySync::MAX_LIMIT */
    public $limit = ManualCompanySync::MAX_LIMIT;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['limit']);
    }

    public function actionCompanies(): int
    {
        foreach (['DB_DSN_EIN_SKIP_TRACING', 'DB_USER_EIN_SKIP_TRACING'] as $k) {
            if (env($k) === null) {
                $this->stderr("Missing setting $k. Set DB_DSN_EIN_SKIP_TRACING, DB_USER_EIN_SKIP_TRACING and DB_PASS_EIN_SKIP_TRACING in .env.\n", Console::FG_RED);
                return ExitCode::CONFIG;
            }
        }
        $mutex = Yii::$app->mutex;
        if (!$mutex->acquire('ein-sync-companies')) {
            $this->stdout("Another ein-sync/companies run is still going; skipping.\n");
            return ExitCode::OK;
        }
        try {
            $r = (new ManualCompanySync(Yii::$app->get('dbEinSkipTracing'), Yii::$app->db))->run((int) $this->limit);
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->stderr(gmdate('Y-m-d H:i:s') . ' ein-sync failed: ' . get_class($e) . ': ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            $mutex->release('ein-sync-companies');
        }

        foreach ($r['warnings'] as $w) {
            $this->stdout("warning: $w\n", Console::FG_YELLOW);
        }
        if ($r['initialized']) {
            $this->stdout(gmdate('Y-m-d H:i:s') . " First run: starting after manual_companies.id {$r['cursor']}. New rows are copied from the next run on.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }
        $this->stdout(sprintf("%s fetched %d: %d inserted, %d updated, %d unchanged, %d failed. Cursor at id %d (newest source id is %d ahead).\n",
            gmdate('Y-m-d H:i:s'), $r['fetched'], $r['inserted'], $r['updated'], $r['unchanged'], $r['failed'], $r['cursor'], $r['lag']),
            $r['failed'] ? Console::FG_YELLOW : Console::FG_GREEN);
        return $r['failed'] ? ExitCode::DATAERR : ExitCode::OK;
    }
}
