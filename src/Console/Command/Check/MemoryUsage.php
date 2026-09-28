<?php declare(strict_types=1);

namespace ServerMonitor\Console\Command\Check;

use ServerMonitor\Console\Command\AbstractMonitorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check:memory', description: 'Report when the average RAM usage over 1 minute exceeds the threshold')]
class MemoryUsage extends AbstractMonitorCommand
{
    private const OPTION_THRESHOLD = 'threshold';
    private const DEFAULT_THRESHOLD_PERCENTAGE = 95;
    private const SAMPLE_COUNT = 60;

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(self::OPTION_THRESHOLD, null, InputOption::VALUE_REQUIRED, 'RAM usage threshold in percent', self::DEFAULT_THRESHOLD_PERCENTAGE);
    }

    protected function check(InputInterface $input, OutputInterface $output): array
    {
        $threshold = $this->getIntOption($input, self::OPTION_THRESHOLD, 1, 100);
        $this->ensureSystemMeetsRequirements();

        $output->writeln(sprintf('Sampling RAM usage for %d seconds...', self::SAMPLE_COUNT));
        $usageStatistics = $this->getSystemMemoryUsageStatistics();
        $averageUsagePercentage = array_sum($usageStatistics) / count($usageStatistics);

        $output->writeln(sprintf(
            'RAM usage (1 min average): %.1f%% (threshold %d%%)',
            $averageUsagePercentage,
            $threshold
        ));

        if ($averageUsagePercentage < $threshold) {
            return [];
        }

        return [
            sprintf(
                'RAM usage has reached %d%% for %s',
                $averageUsagePercentage,
                $_ENV['YOUTRACK_PROJECT_CODE']
            ),
        ];
    }

    /**
     * @return float[]
     */
    private function getSystemMemoryUsageStatistics(): array
    {
        $memoryUsage = [];
        for ($i = 0; $i < self::SAMPLE_COUNT; $i++) {
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
}
