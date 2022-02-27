<?php declare(strict_types=1);

namespace ServerMonitor\CronJob;

class ArchiveScanner implements CronJobInterface
{
    public function run(array $arguments = []): void
    {
        $this->ensureRequiredFieldsAreProvided($arguments);
        $directoryPath = $arguments[0];

        $compressedFiles = $this->getCompressedFilesFromDirectory($directoryPath);
        $this->sendFoundFilesNotificationToSentry($compressedFiles);
    }

    /**
     * @param string[] $arguments
     */
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

    private function getCompressedFilesFromDirectory(string $directoryPath): array
    {
        $parsedPath = rtrim($directoryPath, '/');
        $files = glob("{$parsedPath}/*.{zip,gz}", GLOB_BRACE);

        if ($files === false) {
            throw new \LogicException("Files in '{$parsedPath}/' could not be read");
        }

        return $files;
    }

    /**
     * @param string[] $files
     */
    private function sendFoundFilesNotificationToSentry(array $files): void
    {
        foreach ($files as $file) {
            \Sentry\captureMessage("Compressed file should not exist in: {$file}");
        }
    }
}
