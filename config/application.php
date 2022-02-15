<?php declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

$pathToAutoLoader = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($pathToAutoLoader)) {
    echo "The composer autoloader can not be found, did you forget to run 'composer install --no-dev'?";
    exit(1);
}

require_once $pathToAutoLoader;

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();
$dotenv->required(['SENTRY_DSN', 'PROJECT_NAME', 'YOUTRACK_PROJECT_CODE']);
$dotenv->required(['DEBUG_MODE'])->isBoolean();

if ($_ENV['DEBUG_MODE']) {
    ini_set('display_errors', 'On');
    error_reporting(E_ALL);
}

if (strlen($_ENV['SENTRY_DSN']) > 0) {
    \Sentry\init(['dsn' => $_ENV['SENTRY_DSN']]);

    \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
        $scope->setContext('project', [
            'Project Name' => $_ENV['PROJECT_NAME'],
            'YouTrack Project Code' => $_ENV['YOUTRACK_PROJECT_CODE'],
        ]);
    });
}
