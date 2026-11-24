<?php

namespace app\commands;

use app\services\Webhooks;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class WebhooksController extends Controller
{
    public function actionRun(): int
    {
        $mutex = Yii::$app->mutex;
        if (!$mutex->acquire('webhooks-run')) {
            $this->stdout("Another webhooks/run is still going; skipping.\n");
            return ExitCode::OK;
        }
        try {
            $events = Webhooks::detect();
            $queued = Webhooks::enqueue();
            $result = Webhooks::deliverDue();
            $pruned = Webhooks::prune();
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->stderr(gmdate('Y-m-d H:i:s') . ' webhooks/run failed: ' . get_class($e) . ': ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            $mutex->release('webhooks-run');
        }
        $this->stdout(sprintf("%s new events %d, queued %d, delivered %d, failed %d, pruned %d\n",
            gmdate('Y-m-d H:i:s'), $events, $queued, $result['sent'], $result['failed'], $pruned), Console::FG_GREEN);
        return ExitCode::OK;
    }
}
