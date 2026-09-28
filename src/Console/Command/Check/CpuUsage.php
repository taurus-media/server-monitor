<?php declare(strict_types=1);

namespace ServerMonitor\Console\Command\Check;

use ServerMonitor\Console\Command\AbstractMonitorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check:cpu', description: 'Report when the 15 minute CPU load average exceeds the threshold')]
class CpuUsage extends AbstractMonitorCommand
{
    private const OPTION_THRESHOLD = 'threshold';
    private const DEFAULT_THRESHOLD_PERCENTAGE = 85;

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(self::OPTION_THRESHOLD, null, InputOption::VALUE_REQUIRED, 'Load average threshold in percent of available CPUs', self::DEFAULT_THRESHOLD_PERCENTAGE);
    }

    protected function check(InputInterface $input, OutputInterface $output): array
    {
        $threshold = $this->getIntOption($input, self::OPTION_THRESHOLD, 1, 1000);
        $loadAverageInFifteenMinutes = $this->getSystemLoadAverage()[2];
        $processorCount = $this->getSystemProcessorCount();
        $loadAveragePercentage = ($loadAverageInFifteenMinutes / $processorCount) * 100;

        $output->writeln(sprintf(
            'CPU load (15 min average): %.1f%% (load %.2f on %d CPUs, threshold %d%%)',
            $loadAveragePercentage,
            $loadAverageInFifteenMinutes,
            $processorCount,
            $threshold
        ));

        if ($loadAveragePercentage < $threshold) {
            return [];
        }

        return [
            sprintf(
                'Average CPU load has reached %d%% for %s',
                $loadAveragePercentage,
                $_ENV['YOUTRACK_PROJECT_CODE']
            ),
        ];
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
}
