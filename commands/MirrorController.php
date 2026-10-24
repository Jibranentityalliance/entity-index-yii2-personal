<?php

namespace app\commands;

use app\services\MirrorConverter;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Converts the replicated portal companies (MIRROR_DB.manual_companies) into the portal's entities/people tables.
 *
 *   php yii mirror/backfill              convert every row once (resumable: continues after the last converted id)
 *   php yii mirror/backfill --limit=5000 only the next 5,000 rows
 *   php yii mirror/backfill --restart    start over from the first row (re-converts companies already there)
 *   php yii mirror/apply                 replay changes recorded by the replica triggers (cron, every minute)
 *   php yii mirror/status                progress and queue size
 *
 * backfill and apply share one lock, so cron's apply waits while a backfill runs. See docs/DEPLOY.md.
 */
class MirrorController extends Controller
{
    /** @var int|null rows to convert in this run (backfill) */
    public $limit;
    /** @var int rows per transaction (backfill) */
    public $batch = 1000;
    /** @var bool start over from the first source row (backfill) */
    public $restart = false;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), $actionID === 'backfill' ? ['limit', 'batch', 'restart'] : []);
    }

    public function actionBackfill(): int
    {
        return $this->locked(function (MirrorConverter $c) {
            $start = microtime(true);
            if ($this->restart) {
                $c->setState(MirrorConverter::CURSOR, 0);
            }
            $this->stdout(sprintf("%s backfill from id %d\n", gmdate('Y-m-d H:i:s'), $c->state(MirrorConverter::CURSOR)));
            [$done, $cursor] = $c->backfill(max(1, (int) $this->batch), $this->limit === null ? null : (int) $this->limit,
                function (int $n, int $cursor) use ($start) {
                    if ($n % 50000 < (int) $this->batch) {
                        $this->stdout(sprintf("%s %s rows, at id %d, %.0f rows/s\n", gmdate('H:i:s'), number_format($n), $cursor, $n / max(1, microtime(true) - $start)));
                    }
                });
            $this->stdout(sprintf("%s backfill done: %s rows this run, cursor at id %d\n", gmdate('Y-m-d H:i:s'), number_format($done), $cursor), Console::FG_GREEN);
        });
    }

    public function actionApply(): int
    {
        return $this->locked(function (MirrorConverter $c) {
            $total = [0, 0, 0];
            do {
                $r = $c->applyChanges();
                $total = [$total[0] + $r[0], $total[1] + $r[1], $total[2] + $r[2]];
            } while ($r[0] > 0);
            if ($total[0] > 0) {
                $this->stdout(sprintf("%s applied %d changes: %d rows updated, %d deleted\n", gmdate('Y-m-d H:i:s'), ...$total));
            }
        }, quietIfBusy: true);
    }

    public function actionStatus(): int
    {
        $c = $this->converter();
        $db = Yii::$app->db;
        $this->stdout(sprintf("backfill cursor: id %d\nqueued changes: %d\ncompanies: %d\npeople: %d\n",
            $c->state(MirrorConverter::CURSOR),
            (int) $db->createCommand('SELECT COUNT(*) FROM mirror_changes')->queryScalar(),
            (int) $db->createCommand('SELECT COUNT(*) FROM entities')->queryScalar(),
            (int) $db->createCommand('SELECT COUNT(*) FROM people')->queryScalar()));
        return ExitCode::OK;
    }

    private function locked(callable $fn, bool $quietIfBusy = false): int
    {
        $mutex = Yii::$app->mutex;
        if (!$mutex->acquire('mirror-convert')) {
            if (!$quietIfBusy) {
                $this->stdout("Another mirror run is still going; skipping.\n");
            }
            return ExitCode::OK;
        }
        try {
            $fn($this->converter());
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->stderr(gmdate('Y-m-d H:i:s') . ' mirror failed: ' . get_class($e) . ': ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            $mutex->release('mirror-convert');
        }
        return ExitCode::OK;
    }

    private function converter(): MirrorConverter
    {
        $p = Yii::$app->params;
        return new MirrorConverter(Yii::$app->db, $p['mirrorDb'], new \DateTimeZone($p['mirrorTz']));
    }
}
