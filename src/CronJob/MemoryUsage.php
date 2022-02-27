<?php declare(strict_types=1);

namespace ServerMonitor\CronJob;

class MemoryUsage implements CronJobInterface
{
    private const MAX_LOAD_IN_PERCENT = 95;

    public function run(array $arguments = []): void
    {
        if (!file_exists('/usr/bin/free')) {
            throw new \LogicException("Command 'free' not found, please run 'apt-get install procps'");
        }

        $memoryUsage = [];
        for ($i = 0; $i < 60; $i++) {
            $memoryUsage[] = (float)shell_exec("/usr/bin/free -m | awk 'NR==2{printf \"%.2f\", $3*100/$2 }'");
            sleep(1);
        }

        $average = array_sum($memoryUsage) / count($memoryUsage);
        if ($average >= self::MAX_LOAD_IN_PERCENT) {
            \Sentry\captureMessage(
                sprintf(
                    'RAM usage has reached %d%% for %s',
                    $average,
                    $_ENV['YOUTRACK_PROJECT_CODE']
                )
            );
        }
    }
}
