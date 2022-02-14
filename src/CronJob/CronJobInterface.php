<?php

namespace LockCollector\CronJob;

interface CronJobInterface
{
    public function run(array $arguments = []): void;
}
