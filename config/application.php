<?php declare(strict_types=1);

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
$dotenv->required(['YOUTRACK_PROJECT_CODE']);
