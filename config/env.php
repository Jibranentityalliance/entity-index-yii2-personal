<?php
/**
 * Defines env() and the YII_DEBUG / YII_ENV constants before Yii boots.
 *
 * Lookup order: real environment (php-fpm env[], systemd, Apache SetEnv, shell) first,
 * then the .env file in the project root, then the default.
 */

/** Values parsed from .env (kept out of $_ENV/$_SERVER so real environment variables always win). */
function envFileValues(): array
{
    static $values = null;
    if ($values === null) {
        $root = dirname(__DIR__);
        $values = is_file($root . '/.env')
            ? Dotenv\Dotenv::createArrayBacked($root)->safeLoad()
            : [];
    }
    return $values;
}

/** Read a setting with a default. Empty strings count as unset. */
function env(string $key, $default = null)
{
    foreach ([getenv($key), $_SERVER[$key] ?? false, $_ENV[$key] ?? false, envFileValues()[$key] ?? false] as $v) {
        if ($v !== false && $v !== null && $v !== '') {
            return $v;
        }
    }
    return $default;
}

defined('YII_ENV') or define('YII_ENV', env('YII_ENV', 'prod'));
defined('YII_DEBUG') or define('YII_DEBUG', filter_var(env('YII_DEBUG', YII_ENV !== 'prod'), FILTER_VALIDATE_BOOLEAN));
