<?php declare(strict_types=1);

namespace ServerMonitor\CronJob;

class CpuUsage implements CronJobInterface
{
    private const MAX_LOAD_AVERAGE_IN_PERCENT = 85;

    public function run(array $arguments = []): void
    {
        $systemLoadAverage = $this->getSystemLoadAverage();
        $loadAverageInFifteenMinutes = $systemLoadAverage[2];

        if ($loadAverageInFifteenMinutes >= $this->getAllowedThreshold()) {
            $loadAveragePercentage = ($loadAverageInFifteenMinutes / $this->getSystemProcessorCount()) * 100;
            $this->sendNotificationToSentry($loadAveragePercentage);
        }
    }

    private function getSystemProcessorCount(): int
    {
        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') === 0) {
            $str = trim(@shell_exec('wmic cpu get NumberOfCores 2>&1'));
            if (!preg_match('/(\d+)/', $str, $matches)) {
                throw new \RuntimeException('Failed to detect number of CPUs available on Windows');
            }

            return ((int)$matches[1]);
        }

        if (is_readable('/proc/cpuinfo')) {
            $cpuInfo = file_get_contents('/proc/cpuinfo');
            return substr_count($cpuInfo, 'processor');
        }

        $cpuCount = @shell_exec('nproc');
        if (is_string($cpuCount)) {
            $cpuCount = filter_var(trim($cpuCount), FILTER_VALIDATE_INT);
            if ($cpuCount !== false) {
                return $cpuCount;
            }
        }

        throw new \LogicException("Failed to detect number of CPUs available on system");
    }

    private function getAllowedThreshold(): float
    {
        return $this->getSystemProcessorCount() * (self::MAX_LOAD_AVERAGE_IN_PERCENT / 100);
    }

    /**
     * @return float[]
     */
    private function getSystemLoadAverage(): array
    {
        $systemLoadAverage = sys_getloadavg();
        if ($systemLoadAverage === false) {
            throw new \RuntimeException("Could not get system load average for cpu usage monitor");
        }
        return $systemLoadAverage;
    }

    private function sendNotificationToSentry(float $loadAverage) : void
    {
        \Sentry\captureMessage(
            sprintf(
                'Average CPU load has reached %d%% for %s',
                $loadAverage,
                $_ENV['YOUTRACK_PROJECT_CODE']
            )
        );
    }
}
