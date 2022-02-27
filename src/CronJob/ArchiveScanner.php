<?php declare(strict_types=1);

namespace ServerMonitor\CronJob;

class ArchiveScanner implements CronJobInterface
{
    public function run(array $arguments = []): void
    {
        $this->ensureRequiredFieldsAreProvided($arguments);
        $pathToFile = rtrim($arguments[0], '/');

        $archives = glob("{$pathToFile}/*.{zip,gz}", GLOB_BRACE);
        foreach ($archives as $fileName) {
            \Sentry\captureMessage("ZIP file should not exist in: {$fileName}");
        }
    }

    private function ensureRequiredFieldsAreProvided(array $arguments): void
    {
        $scanPath = $arguments[0] ?? null;
        if ($scanPath === null || $scanPath === '') {
            throw new \LogicException("A scan path needs to be provided in order to execute this cronjob");
        }
    }
}
