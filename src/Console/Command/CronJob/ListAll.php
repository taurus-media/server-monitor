<?php declare(strict_types=1);

namespace ServerMonitor\Console\Command\CronJob;

use ServerMonitor\Service\CronJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListAll extends Command
{
    public function configure(): void
    {
        $this->setName('cronjob:list')
            ->setDescription('List all available Cronjobs');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $service = new CronJobService();
        $cronjobs = $service->getAllCronjobs();

        $rows = [];
        foreach ($cronjobs as $cronjob) {
            $rows[] = [$cronjob];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['Available CronJobs'])
            ->setRows($rows);
        $table->render();

        return 0;
    }
}
