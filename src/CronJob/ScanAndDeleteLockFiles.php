<?php declare(strict_types=1);

namespace LockCollector\CronJob;

class ScanAndDeleteLockFiles implements CronJobInterface
{
    /**
     * Required arguments:
     * - string $arguments[0] scanPath, where lock files should be searched
     *
     * @param array $arguments
     */
    public function run(array $arguments = []): void
    {
        $this->ensureRequiredFieldsAreProvided($arguments);
        $scanPath = $arguments[0];

        $lockFiles = glob("{$scanPath}/*.lock");
        if ($lockFiles === false) {
            throw new \LogicException("Lock files in '{$scanPath}/' could not be read");
        }

        foreach ($lockFiles as $lockFile) {
            $lastModifiedTimestamp = filemtime($lockFile);
            $lastModifiedDate = new \DateTime("@{$lastModifiedTimestamp}");

            $timeNow = new \DateTime();
            $timeNow->sub(new \DateInterval('PT30M'));

            // When lock file is older than 30 mins
            if ($lastModifiedDate < $timeNow) {
                $isDeleted = unlink($lockFile);
                if (!$isDeleted) {
                    throw new \RuntimeException("Error removing file: {$lockFile}");
                }

                $lockFileName = str_replace("{$scanPath}/", '', $lockFile);
                throw new \RuntimeException("Lock file[name={$lockFileName}] found, last modified 30+ minutes ago. Lock file removed.");
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
