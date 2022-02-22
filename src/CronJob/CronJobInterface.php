<?php declare(strict_types=1);

namespace ServerMonitor\CronJob;

interface CronJobInterface
{
    public function run(array $arguments = []): void;
}
