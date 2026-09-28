<?php declare(strict_types=1);

namespace ServerMonitor\Console\Command\Cron;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cron:install', description: 'Add the checks to the crontab of the current user, skipping the ones already present')]
class Install extends Command
{
    private const CRONTAB_HEADER = '# Server Monitor';
    private const PHP_BINARY = '/usr/bin/php';

    /**
     * Command name => [schedule, arguments].
     */
    private const JOBS = [
        'check:cpu' => ['*/15 * * * *', ''],
        'check:memory' => ['*/15 * * * *', ''],
        'check:cron' => ['*/10 * * * *', ''],
        'check:cron-job-execution' => ['0 7 * * *', ''],
        'check:large-files' => ['0 3 * * *', ''],
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $crontab = $this->readCrontab();

        $newLines = [];
        foreach (self::JOBS as $command => [$schedule, $arguments]) {
            if ($this->hasCommand($crontab, $command)) {
                $output->writeln("<comment>{$command} is already added to the crontab.</comment>");
                continue;
            }

            $newLines[] = implode(' ', array_filter([
                $schedule,
                $this->getPhpBinary(),
                BASE_PATH . '/bin/console',
                $command,
                $arguments,
                '--notify',
            ]));
            $output->writeln("<info>{$command} added to the crontab.</info>");
        }

        if ($newLines === []) {
            return self::SUCCESS;
        }

        if (!str_contains($crontab, self::CRONTAB_HEADER)) {
            array_unshift($newLines, self::CRONTAB_HEADER);
        }

        $crontab = rtrim($crontab);
        $crontab = ($crontab === '' ? '' : $crontab . "\n\n") . implode("\n", $newLines) . "\n";
        $this->writeCrontab($crontab);

        return self::SUCCESS;
    }

    /**
     * Prefers the unversioned binary, so the crontab survives PHP version switches.
     */
    private function getPhpBinary(): string
    {
        return is_executable(self::PHP_BINARY) ? self::PHP_BINARY : PHP_BINARY;
    }

    /**
     * Checks for an active (not commented out) crontab line running the given console command.
     */
    private function hasCommand(string $crontab, string $command): bool
    {
        $pattern = '~^\s*[^#\s].*\bconsole[\'"]?\s+' . preg_quote($command, '~') . '(\s|$)~m';

        return preg_match($pattern, $crontab) === 1;
    }

    private function readCrontab(): string
    {
        [$exitCode, $stdout, $stderr] = $this->runCrontab(['-l']);
        if ($exitCode === 0) {
            return $stdout;
        }

        // crontab -l fails when the user has no crontab yet.
        if (stripos($stderr, 'no crontab') !== false) {
            return '';
        }

        throw new \RuntimeException('Could not read the crontab: ' . trim($stderr));
    }

    private function writeCrontab(string $crontab): void
    {
        [$exitCode, , $stderr] = $this->runCrontab(['-'], $crontab);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Could not write the crontab: ' . trim($stderr));
        }
    }

    /**
     * @param string[] $arguments
     * @return array{int, string, string} exit code, stdout, stderr
     */
    private function runCrontab(array $arguments, string $stdin = ''): array
    {
        $process = proc_open(['crontab', ...$arguments], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not run crontab');
        }

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
