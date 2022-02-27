<?php declare(strict_types=1);

namespace ServerMonitor\CronJob;

class MemoryUsage implements CronJobInterface
{
    private const ALLOWED_THRESHOLD_PERCENTAGE = 95;

    public function run(array $arguments = []): void
    {
        $this->ensureSystemMeetsRequirements();

        $usageStatistics = $this->getSystemMemoryUsageStatistics();
        $averageUsagePercentage = $this->getCalculatedAverageUsagePercentage($usageStatistics);

        if ($averageUsagePercentage >= self::ALLOWED_THRESHOLD_PERCENTAGE) {
            $this->sendNotificationToSentry($averageUsagePercentage);
        }
    }

    private function getCalculatedAverageUsagePercentage(array $usageStatistics): float
    {
        return array_sum($usageStatistics) / count($usageStatistics);
    }

    /**
     * @return float[]
     */
    private function getSystemMemoryUsageStatistics(): array
    {
        $memoryUsage = [];
        for ($i = 0; $i < 60; $i++) {
            $memoryUsage[] = (float)shell_exec("/usr/bin/free -m | awk 'NR==2{printf \"%.2f\", $3*100/$2 }'");
            sleep(1);
        }
        return $memoryUsage;
    }

    private function ensureSystemMeetsRequirements(): void
    {
        if (!file_exists('/usr/bin/free')) {
            throw new \LogicException("Command 'free' not found, please run 'apt-get install procps'");
        }
    }

    private function sendNotificationToSentry(float $averageUsagePercentage): void
    {
        \Sentry\captureMessage(
            sprintf(
                'RAM usage has reached %d%% for %s',
                $averageUsagePercentage,
                $_ENV['YOUTRACK_PROJECT_CODE']
            )
        );
    }
}
