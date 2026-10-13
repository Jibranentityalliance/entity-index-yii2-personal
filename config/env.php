<?php
/**
 * Settings loader: defines env(), envRequired(), envBool() and the YII_DEBUG / YII_ENV constants before Yii boots.
 *
 * Lookup order: real environment (php-fpm env[], systemd, Apache SetEnv, shell) first,
 * then the .env file in the project root (or the file named by the ENV_FILE environment variable). A missing setting is `null` unless a default is passed.
 *
 * Settings that have NO safe default and must always be provided (checked at boot by envAssertRequired()):
 * see envRequiredKeys().
 */

/** Settings the app cannot run without. DB_PASS may legitimately be empty, so it is not listed. */
function envRequiredKeys(): array
{
    return ['DB_DSN', 'DB_USER', 'COOKIE_VALIDATION_KEY', 'SITE_URL'];
}

/** Values parsed from .env (kept out of $_ENV/$_SERVER so real environment variables always win). */
function envFileValues(): array
{
    static $values = null;
    if ($values === null) {
        $root = dirname(__DIR__);
        // ENV_FILE (real environment only) points at another env file, e.g. one kept outside the project folder.
        $file = getenv('ENV_FILE') ?: $root . '/.env';
        if (!is_file($file)) {
            return $values = [];
        }
        if (!is_readable($file)) {
            $user = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : get_current_user();
            throw new RuntimeException(".env exists but is not readable by the PHP user '$user'. Fix its owner/mode, e.g. chown deploy:www-data .env && chmod 640 .env");
        }
        try {
            $values = Dotenv\Dotenv::createArrayBacked(dirname($file), basename($file))->safeLoad();
        } catch (Dotenv\Exception\InvalidFileException $e) {
            // The library message quotes the offending value; never log that.
            throw new RuntimeException('.env could not be parsed. Wrap values that contain spaces, # or $ in double quotes, e.g. DB_PASS="p#ss word".');
        }
    }
    return $values;
}

/** Read a setting. Empty strings count as unset. Returns $default (null) when missing. */
function env(string $key, $default = null)
{
    foreach ([getenv($key), $_SERVER[$key] ?? false, $_ENV[$key] ?? false, envFileValues()[$key] ?? false] as $v) {
        if ($v !== false && $v !== null && $v !== '') {
            return $v;
        }
    }
    return $default;
}

/** Read a setting that must exist; throws a clear error naming it (never its value). */
function envRequired(string $key): string
{
    $v = env($key);
    if ($v === null) {
        throw new RuntimeException("Missing required setting $key. Set it in .env (readable by the web server user) or as an environment variable.");
    }
    return (string) $v;
}

/** Boolean setting. Anything that is not clearly true/false falls back to $default. */
function envBool(string $key, bool $default): bool
{
    $v = env($key);
    return $v === null ? $default : (filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default);
}

/** Throws one error listing every missing required setting. */
function envAssertRequired(): void
{
    $missing = array_values(array_filter(envRequiredKeys(), fn($k) => env($k) === null));
    if ($missing) {
        throw new RuntimeException('Missing required setting' . (count($missing) > 1 ? 's' : '') . ': ' . implode(', ', $missing)
            . '. Set each in .env (readable by the web server user) or as an environment variable. Run "php yii env/check" for details.');
    }
}

/** Logs a boot/config failure (no secrets) and stops with a clear message. Used by web/index.php and yii. */
function envFail(Throwable $e, bool $cli = false): void
{
    $line = date('Y-m-d H:i:s') . ' ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    error_log('[entityindex] ' . trim($line));
    $dir = dirname(__DIR__) . '/runtime/logs';
    if (is_dir($dir) || @mkdir($dir, 0775, true)) {
        @file_put_contents($dir . '/config-error.log', $line, FILE_APPEND);
    }
    $debug = filter_var(getenv('YII_DEBUG') ?: ($_SERVER['YII_DEBUG'] ?? ''), FILTER_VALIDATE_BOOLEAN);
    if ($cli) {
        fwrite(STDERR, "Configuration error: " . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $debug
        ? 'Configuration error: ' . $e->getMessage()
        : "Configuration error. See runtime/logs/config-error.log or the web server error log, and run `php yii env/check` on the server.";
    exit(1);
}

defined('YII_ENV') or define('YII_ENV', env('YII_ENV', 'prod'));
defined('YII_DEBUG') or define('YII_DEBUG', envBool('YII_DEBUG', YII_ENV !== 'prod'));
