<?php declare(strict_types=1);

namespace ServerMonitor\Console\Command\Check;

use ServerMonitor\Console\Command\AbstractMonitorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check:large-files', description: 'Report files larger than the threshold in a directory (recursive)')]
class LargeFileScanner extends AbstractMonitorCommand
{
    private const ARGUMENT_PATH = 'path';
    private const OPTION_THRESHOLD = 'threshold';
    private const DEFAULT_THRESHOLD = '100M';
    private const UNITS = ['B', 'K', 'M', 'G', 'T'];

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument(self::ARGUMENT_PATH, InputArgument::OPTIONAL, 'Directory to scan recursively (defaults to MAGENTO_ROOT from .env)');
        $this->addOption(self::OPTION_THRESHOLD, null, InputOption::VALUE_REQUIRED, 'File size threshold, e.g. 500K, 200M or 1G', self::DEFAULT_THRESHOLD);
    }

    protected function check(InputInterface $input, OutputInterface $output): array
    {
        $scanPath = $input->getArgument(self::ARGUMENT_PATH) === null
            ? $this->getMagentoRoot()
            : $this->getDirectoryArgument($input, self::ARGUMENT_PATH);
        $threshold = $this->getSizeOption($input, self::OPTION_THRESHOLD);

        $largeFiles = $this->findLargeFiles($scanPath, $threshold);
        arsort($largeFiles);

        $output->writeln(sprintf('Files larger than %s in %s: %d', $this->formatSize($threshold), $scanPath, count($largeFiles)));

        $messages = [];
        foreach ($largeFiles as $file => $size) {
            $messages[] = sprintf('Large file (%s) found: %s for %s', $this->formatSize($size), $file, $_ENV['YOUTRACK_PROJECT_CODE']);
        }

        return $messages;
    }

    /**
     * @return array<string, int> file path => size in bytes
     */
    private function findLargeFiles(string $scanPath, int $threshold): array
    {
        // Symlinks are not followed, unreadable directories are skipped.
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scanPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $largeFiles = [];
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) {
                continue;
            }

            $size = $file->getSize();
            if ($size > $threshold) {
                $largeFiles[$file->getPathname()] = $size;
            }
        }

        return $largeFiles;
    }

    /**
     * Parses sizes like "1024", "500K", "200M", "200Mb" or "1.5G" (binary units) into bytes.
     */
    private function getSizeOption(InputInterface $input, string $name): int
    {
        $value = trim((string)$input->getOption($name));
        if (!preg_match('~^(\d+(?:\.\d+)?)\s*([KMGT]?)B?$~i', $value, $matches)) {
            throw new InvalidOptionException("The --{$name} option must be a size like 500K, 200M or 1G.");
        }

        $exponent = (int)array_search(strtoupper($matches[2] ?: 'B'), self::UNITS, true);
        $bytes = (int)((float)$matches[1] * 1024 ** $exponent);
        if ($bytes < 1) {
            throw new InvalidOptionException("The --{$name} option must be greater than zero.");
        }

        return $bytes;
    }

    private function formatSize(int $bytes): string
    {
        $exponent = min((int)floor(log(max($bytes, 1), 1024)), count(self::UNITS) - 1);
        $unit = self::UNITS[$exponent];

        return $exponent === 0
            ? "{$bytes} B"
            : sprintf('%s %sB', rtrim(rtrim(number_format($bytes / 1024 ** $exponent, 1, '.', ''), '0'), '.'), $unit);
    }
}
