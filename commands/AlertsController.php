<?php

namespace app\commands;

use app\services\Alerts;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class AlertsController extends Controller
{
    public $force = false;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), $actionID === 'send' ? ['force'] : []);
    }

    public function actionSend(): int
    {
        $mutex = Yii::$app->mutex;
        if (!$mutex->acquire('alerts-send')) {
            $this->stdout("Another alerts/send run is still going; skipping.\n");
            return ExitCode::OK;
        }
        try {
            $s = Alerts::run((bool) $this->force);
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->stderr(gmdate('Y-m-d H:i:s') . ' alerts/send failed: ' . get_class($e) . ': ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            $mutex->release('alerts-send');
        }
        $this->stdout(sprintf("%s saved searches checked %d, emails sent %d (%s new companies), failed %d, skipped %d\n",
            gmdate('Y-m-d H:i:s'), $s['checked'], $s['sent'], number_format($s['companies']), $s['failed'], $s['skipped']), $s['failed'] ? Console::FG_YELLOW : Console::FG_GREEN);
        return ExitCode::OK;
    }
}
