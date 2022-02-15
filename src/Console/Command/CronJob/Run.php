<?php declare(strict_types=1);

namespace ServerMonitor\Console\Command\CronJob;

use ServerMonitor\Service\CronJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Run extends Command
{
    private const FIELD_CRONJOB_NAME = 'name';
    private const FIELD_CRONJOB_ARGUMENTS = 'arguments';

    public function configure(): void
    {
        $this->setName('cronjob:run')
            ->setDescription('Run a cronjob')
            ->addArgument(self::FIELD_CRONJOB_NAME, InputArgument::REQUIRED, 'CronJob name')
            ->addArgument(self::FIELD_CRONJOB_ARGUMENTS, InputArgument::OPTIONAL, 'Cronjob arguments comma separated', []);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $cronjobName = $input->getArgument(self::FIELD_CRONJOB_NAME);
        $cronjobArguments = $input->getArgument(self::FIELD_CRONJOB_ARGUMENTS);

        if ($cronjobArguments !== []) {
            $cronjobArguments = explode(',', $cronjobArguments);
        }

        $service = new CronJobService();
        $service->execute($cronjobName, $cronjobArguments);

        return 0;
    }
}
