<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Diagnoses configuration problems (the usual cause of "An internal server error occurred." on a new server).
 *
 *   php yii env/check
 *
 * Never prints secret values, only whether they are set.
 */
class EnvController extends Controller
{
    private const SECRET = ['DB_USER', 'DB_PASS', 'COOKIE_VALIDATION_KEY', 'DB_DSN', 'DB_DSN_EIN_SKIP_TRACING', 'DB_USER_EIN_SKIP_TRACING', 'DB_PASS_EIN_SKIP_TRACING'];

    public function actionCheck(): int
    {
        $ok = true;
        $root = Yii::getAlias('@app');
        $file = getenv('ENV_FILE') ?: $root . '/.env';
        $user = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : get_current_user();
        $this->stdout("Running as user: $user\n");

        if (!is_file($file)) {
            $this->stdout("env file ($file): not found (settings must come from real environment variables)\n", Console::FG_YELLOW);
        } elseif (!is_readable($file)) {
            $this->stdout(".env: EXISTS BUT NOT READABLE by '$user'. Run: chown deploy:www-data .env && chmod 640 .env\n", Console::FG_RED);
            $ok = false;
        } else {
            $this->stdout('.env: found, readable, mode ' . substr(sprintf('%o', fileperms($file)), -4) . "\n", Console::FG_GREEN);
        }

        try {
            envFileValues();
        } catch (\Throwable $e) {
            $this->stdout('.env: ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\nRequired settings:\n");
        foreach (envRequiredKeys() as $k) {
            $set = env($k) !== null;
            $ok = $ok && $set;
            $this->stdout(sprintf("  %-26s %s\n", $k, $set ? 'set' : 'MISSING'), $set ? Console::FG_GREEN : Console::FG_RED);
        }

        $this->stdout("\nOptional settings:\n");
        foreach (['DB_PASS' => '', 'YII_ENV' => 'prod', 'YII_DEBUG' => '', 'SITE_MODE' => 'live', 'SITE_NOINDEX' => 'true', 'ADMIN_EMAIL' => '',
            'MIRROR_DB' => 'ein_skip_tracing', 'MIRROR_TZ' => 'America/New_York', 'DB_DSN_EIN_SKIP_TRACING' => '', 'DB_USER_EIN_SKIP_TRACING' => '', 'DB_PASS_EIN_SKIP_TRACING' => ''] as $k => $default) {
            $v = env($k);
            $shown = $v === null ? ($default !== '' ? "not set (default: $default)" : 'not set') : (in_array($k, self::SECRET, true) ? 'set' : "set ($v)");
            $this->stdout(sprintf("  %-26s %s\n", $k, $shown));
        }
        $this->stdout(sprintf("\nEffective: environment=%s debug=%s noindex=%s\n", YII_ENV, YII_DEBUG ? 'on' : 'off', envBool('SITE_NOINDEX', true) ? 'ON (search engines blocked)' : 'OFF (indexable)'));

        if (env('DB_DSN') !== null && env('DB_USER') !== null) {
            try {
                $pdo = new \PDO((string) env('DB_DSN'), (string) env('DB_USER'), (string) env('DB_PASS', ''));
                $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
                $this->stdout("\nDatabase: connected ($tables tables)" . ($tables === 0 ? ' - empty, run: php yii migrate' : '') . "\n", Console::FG_GREEN);
            } catch (\Throwable $e) {
                $hint = [1045 => 'access denied: wrong DB_USER or DB_PASS', 1049 => 'unknown database: wrong database name in DB_DSN', 2002 => 'cannot reach the server: wrong host/port in DB_DSN or MySQL is not running'][(int) $e->getCode()] ?? 'check DB_DSN, DB_USER, DB_PASS and that MySQL is running';
                $this->stdout("\nDatabase: CONNECTION FAILED (error " . $e->getCode() . "): $hint\n", Console::FG_RED);
                $ok = false;
            }
        }

        foreach (['runtime', 'web/assets'] as $dir) {
            $w = is_writable("$root/$dir");
            $ok = $ok && $w;
            $this->stdout(sprintf("%-12s %s\n", "$dir/", $w ? 'writable' : "NOT WRITABLE by '$user'"), $w ? Console::FG_GREEN : Console::FG_RED);
        }

        $this->stdout($ok ? "\nAll good.\n" : "\nProblems found (see above).\n", $ok ? Console::FG_GREEN : Console::FG_RED);
        return $ok ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
