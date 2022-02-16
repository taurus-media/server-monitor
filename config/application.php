<?php declare(strict_types=1);

use ServerMonitor\Utility\Util;

/**
 * Default PHP settings
 */
ini_set('display_errors', 'On');
error_reporting(E_ALL);

/**
 * Global constants
 */
define('BASE_PATH', dirname(__DIR__));

/**
 * Composer autoloader
 */
$pathToAutoLoader = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($pathToAutoLoader)) {
    echo "The composer autoloader can not be found, did you forget to run 'composer install --no-dev'?\n";
    exit(1);
}

require_once $pathToAutoLoader;

if (!file_exists(BASE_PATH . '/.env')) {
    echo "Please rename and configure the .env.example file in the project root directory\n";
    exit(1);
}

/**
 * Load environment variables
 */
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();
$dotenv->required(['SENTRY_DSN', 'PROJECT_NAME', 'YOUTRACK_PROJECT_CODE']);
$dotenv->required(['DEBUG_MODE'])->isBoolean();

/**
 * Initialize sentry
 */
if (Util::stringContains($_ENV['SENTRY_DSN'], 'https://') === false) {
    echo "Please provide a valid SENTRY_DSN url in your .env file\n";
    exit(1);
}

\Sentry\init(['dsn' => $_ENV['SENTRY_DSN']]);

\Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
    $scope->setContext('project', [
        'Project Name' => $_ENV['PROJECT_NAME'],
        'YouTrack Project Code' => $_ENV['YOUTRACK_PROJECT_CODE'],
    ]);
});
