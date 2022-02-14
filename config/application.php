<?php declare(strict_types=1);

ini_set('display_errors', 'On');
error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));

$pathToAutoLoader = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($pathToAutoLoader)) {
    echo "The composer autoloader can not be found, did you forget to run 'composer install --no-dev'?";
    exit(1);
}

require_once $pathToAutoLoader;

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();
$dotenv->required(['SENTRY_DSN']);
$dotenv->required(['DEBUG_MODE', 'SENTRY_ENABLED'])->isBoolean();

if ($_ENV['SENTRY_ENABLED']) {
    \Sentry\init(['dsn' => $_ENV['SENTRY_DSN']]);
}
