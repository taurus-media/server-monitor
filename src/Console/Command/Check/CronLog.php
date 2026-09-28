<?php declare(strict_types=1);

namespace ServerMonitor\Console\Command\Check;

use ServerMonitor\Console\Command\AbstractMonitorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check:cron', description: 'Report when Magento cron.log has not been updated within the threshold')]
class CronLog extends AbstractMonitorCommand
{
    private const OPTION_THRESHOLD = 'threshold';
    private const DEFAULT_THRESHOLD_IN_MINS = 10;
    private const CRON_LOG_PATH = '/var/log/cron.log';

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(self::OPTION_THRESHOLD, null, InputOption::VALUE_REQUIRED, 'Report when cron.log is older than this many minutes', self::DEFAULT_THRESHOLD_IN_MINS);
    }

    protected function check(InputInterface $input, OutputInterface $output): array
    {
        $threshold = $this->getIntOption($input, self::OPTION_THRESHOLD, 1);
        $cronLogFile = $this->getMagentoRoot() . self::CRON_LOG_PATH;

        $lastModified = file_exists($cronLogFile) ? filemtime($cronLogFile) : false;
        if ($lastModified === false) {
            return ["Magento cron log {$cronLogFile} does not exist for {$_ENV['YOUTRACK_PROJECT_CODE']}"];
        }

        $minutesAgo = intdiv(max(0, time() - $lastModified), 60);
        $output->writeln("Magento cron.log last updated {$minutesAgo} min ago (threshold {$threshold} min)");

        if ($minutesAgo <= $threshold) {
            return [];
        }

        return ["Magento cron seems stuck: {$cronLogFile} last updated {$minutesAgo} minutes ago for {$_ENV['YOUTRACK_PROJECT_CODE']}"];
    }

    private function getMagentoRoot(): string
    {
        $magentoRoot = rtrim(trim((string)($_ENV['MAGENTO_ROOT'] ?? '')), '/');
        if ($magentoRoot === '') {
            throw new \LogicException('Please configure MAGENTO_ROOT in your .env file');
        }

        if (!is_dir($magentoRoot)) {
            throw new \LogicException("MAGENTO_ROOT directory '{$magentoRoot}' does not exist");
        }

        return $magentoRoot;
    }
}
