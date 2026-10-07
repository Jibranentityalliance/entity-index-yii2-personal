<?php
/**
 * Router for PHP's built-in web server (local development only):
 *   php yii serve --router=config/dev-router.php
 * Needed so URLs ending in .xml (sitemaps) reach Yii instead of 404ing as missing static files.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/../web' . $path;
if ($path !== '/' && is_file($file)) {
    return false; // serve static asset
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ . '/../web/index.php');
require $_SERVER['SCRIPT_FILENAME'];
