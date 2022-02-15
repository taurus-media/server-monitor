<?php declare(strict_types=1);

namespace LockCollector\Service;

use LockCollector\CronJob\CronJobInterface;

class CronJobService
{
    private const CRONJOB_DIR_PATH = BASE_PATH . '/src/CronJob';
    private const CRONJOB_NAMESPACE = '\\LockCollector\\CronJob';
    /**
     * @return string[]
     */
    public function getAllCronjobs(): array
    {
        $cronjobs = glob(self::CRONJOB_DIR_PATH . '/*.php');
        if ($cronjobs === false) {
            throw new \LogicException("Could not read cronjobs directory");
        }

        $fileNames = [];
        foreach ($cronjobs as $cronjobFile) {
            $fileName = str_replace([self::CRONJOB_DIR_PATH, '/', '.php'], '', $cronjobFile);

            $fileNames[] = $fileName;
        }

        return $fileNames;
    }

    /**
     * Checks to see if the provided class exists, and returns a logic exception if not
     *
     * @param string $fullClassName class name including namespace
     * @return void
     */
    private function ensureCronjobExists(string $fullClassName): void
    {
        if (!class_exists($fullClassName)) {
            throw new \LogicException("CronJob[class={$fullClassName}] does not exist");
        }
    }

    public function execute(string $cronjobName, array $cronjobArguments = []): void
    {
        $cronjobFullClassName = self::CRONJOB_NAMESPACE . "\\{$cronjobName}";
        $this->ensureCronjobExists($cronjobFullClassName);

        /** @var CronJobInterface $cronjob */
        $cronjob = new $cronjobFullClassName();
        $cronjob->run($cronjobArguments);
    }
}
