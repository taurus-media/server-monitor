<?php declare(strict_types=1);

namespace ServerMonitor\CronJob;

class LockCollector implements CronJobInterface
{
    private const DEFAULT_MAX_AGE_IN_MINS = 30;

    /**
     * Scans and deleted lock files older than 30 minutes (default)
     *
     * Required arguments:
     * - string $arguments[0] scanPath, where lock files should be searched
     * - int $arguments[1] (optional) time in minutes
     *
     * @param array $arguments
     */
    public function run(array $arguments = []): void
    {
        $this->ensureRequiredFieldsAreProvided($arguments);
        $scanPath = $arguments[0];
        $maxAgeInMins = preg_replace('/\D/', '', $arguments[1] ?? self::DEFAULT_MAX_AGE_IN_MINS);

        $lockFiles = glob("{$scanPath}/*.lock");
        if ($lockFiles === false) {
            throw new \LogicException("Lock files in '{$scanPath}/' could not be read");
        }

        foreach ($lockFiles as $lockFile) {
            $lastModifiedTimestamp = filemtime($lockFile);
            $lastModifiedDate = new \DateTime("@{$lastModifiedTimestamp}");

            $timeNow = new \DateTime();
            $timeNow->sub(new \DateInterval("PT{$maxAgeInMins}M"));

            // When lock file is older than 30 mins
            if ($lastModifiedDate < $timeNow) {
                $isDeleted = unlink($lockFile);
                if (!$isDeleted) {
                    throw new \RuntimeException("Unable to remove lock file {$lockFile}");
                }

                $lockFileName = str_replace("{$scanPath}/", '', $lockFile);
                \Sentry\captureMessage("Lock file[name={$lockFileName}] found, last modified 30+ minutes ago. Lock file removed.");
            }
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
