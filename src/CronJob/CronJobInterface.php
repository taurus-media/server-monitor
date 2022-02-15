<?php

namespace ServerMonitor\CronJob;

interface CronJobInterface
{
    public function run(array $arguments = []): void;
}
