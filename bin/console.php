#!/usr/bin/env php
<?php declare(strict_types=1);

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;

require_once dirname(__DIR__) . '/config/application.php';

$app = new Application("Server Monitor");

$finder = new Finder();
$finder->files()->in(dirname(__DIR__) . '/src/Console/Command');

$namespace = '\ServerMonitor\Console\Command\\';
foreach ($finder as $file) {
    $className = str_replace('.php', '', $file->getRelativePathname());
    $className = str_replace('/', '\\', $className);

    $fullClassName = "{$namespace}{$className}";

    $reflectionClass = new ReflectionClass($fullClassName);
    if ($reflectionClass->isInstantiable() && $reflectionClass->isSubclassOf(Command::class)) {
        $app->add(new $fullClassName);
    }
}

$app->run();
