<?php declare(strict_types=1);

namespace ServerMonitor\Console\Command;

use ServerMonitor\Notifier\NotifierFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints detected issues and pushes them to the notifier when --notify is given.
 */
abstract class AbstractMonitorCommand extends Command
{
    private const OPTION_NOTIFY = 'notify';

    /**
     * @return string[] detected issues, empty when everything is fine
     */
    abstract protected function check(InputInterface $input, OutputInterface $output): array;

    protected function configure(): void
    {
        $this->addOption(self::OPTION_NOTIFY, null, InputOption::VALUE_NONE, 'Push detected issues to the notifier configured in .env');
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $messages = $this->check($input, $output);

        if ($messages === []) {
            $output->writeln('<info>No issues detected.</info>');
            return self::SUCCESS;
        }

        foreach ($messages as $message) {
            $output->writeln("<comment>{$message}</comment>");
        }

        if ($input->getOption(self::OPTION_NOTIFY)) {
            (new NotifierFactory())->create()->notify((string)$this->getName(), $messages);
            $output->writeln('<info>Notification sent.</info>');
        }

        return self::SUCCESS;
    }

    protected function getIntOption(InputInterface $input, string $name, int $min, ?int $max = null): int
    {
        $value = filter_var($input->getOption($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max ?? PHP_INT_MAX]]);
        if ($value === false) {
            $range = $max === null ? "of at least {$min}" : "between {$min} and {$max}";
            throw new InvalidOptionException("The --{$name} option must be an integer {$range}.");
        }

        return $value;
    }

    protected function getDirectoryArgument(InputInterface $input, string $name): string
    {
        $path = rtrim((string)$input->getArgument($name), '/');
        if (!is_dir($path)) {
            throw new \LogicException("Scan directory '{$path}' does not exist");
        }

        return $path;
    }
}
