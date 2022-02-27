<?php declare(strict_types=1);

namespace ServerMonitor\CronJob;

class LockCollector implements CronJobInterface
{
    private const DEFAULT_MAX_AGE_IN_MINS = 30;

    /** @var string */
    private $scanPath;

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
        $this->scanPath = $arguments[0];
        $maxFileAgeThresholdInMinutes = $this->getMaxFileAgeThresholdInMinutes($arguments);

        $lockFiles = $this->getLockFilesFromDirectory($this->scanPath);
        $this->deleteLockFilesAndSendNotificationToSentry($lockFiles, (int)$maxFileAgeThresholdInMinutes);
    }

    private function getMaxFileAgeThresholdInMinutes(array $arguments)
    {
        return preg_replace('/\D/', '', $arguments[1] ?? self::DEFAULT_MAX_AGE_IN_MINS);
    }

    private function deleteLockFilesAndSendNotificationToSentry(array $lockFiles, int $maxFileAgeThresholdInMinutes): void
    {
        foreach ($lockFiles as $lockFile) {
            $lockFileAge = $this->getDateTimeFromUnixTimestamp(filemtime($lockFile));
            $fileAgeThreshold = $this->getDateTimeWithMinutesSubtracted($maxFileAgeThresholdInMinutes);

            if ($lockFileAge < $fileAgeThreshold) {
                $this->deleteFile($lockFile);
                $this->sendNotificationToSentry($lockFile);
            }
        }
    }

    private function getDateTimeWithMinutesSubtracted(int $minutesToSubtract): \DateTime
    {
        $time = new \DateTime();
        $time->sub(new \DateInterval("PT{$minutesToSubtract}M"));
        return $time;
    }

    private function getDateTimeFromUnixTimestamp(int $timestamp): \DateTime
    {
        return new \DateTime("@{$timestamp}");
    }

    /**
     * @return string[]
     */
    private function getLockFilesFromDirectory(string $path): array
    {
        $parsedPath = rtrim($path, '/');
        $files = glob("{$parsedPath}/{.[!.],}*.lock", GLOB_BRACE);

        if ($files === false) {
            throw new \LogicException("Files in '{$parsedPath}/' could not be read");
        }

        return $files;
    }

    private function ensureRequiredFieldsAreProvided(array $arguments): void
    {
        $scanPath = $arguments[0] ?? null;
        if ($scanPath === null || $scanPath === '') {
            throw new \LogicException("A scan path needs to be provided in order to execute this cronjob");
        }

        if (!is_dir($scanPath)) {
            throw new \LogicException("Scan directory '{$scanPath}' does not exist");
        }
    }

    private function sendNotificationToSentry(string $lockFile): void
    {
        $lockFileName = str_replace("{$this->scanPath}/", '', $lockFile);
        \Sentry\captureMessage("Lock file[name={$lockFileName}] found, last modified 30+ minutes ago. Lock file removed.");
    }

    private function deleteFile(string $file): void
    {
        $isDeleted = unlink($file);
        if (!$isDeleted) {
            throw new \RuntimeException("Unable to remove file {$file}");
        }
    }
}
