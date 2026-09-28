<?php declare(strict_types=1);

namespace ServerMonitor\Console\Command\Check;

use ServerMonitor\Console\Command\AbstractMonitorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check:cron-job-execution', description: 'Report Magento cron jobs started in the last 24h whose execution took longer than the threshold')]
class CronJobExecution extends AbstractMonitorCommand
{
    private const OPTION_THRESHOLD = 'threshold';
    private const DEFAULT_THRESHOLD_IN_SECS = 120;
    private const MYSQL_CONFIG_FILE = '.my.cnf';

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(self::OPTION_THRESHOLD, null, InputOption::VALUE_REQUIRED, 'Report cron jobs that ran longer than this many seconds', self::DEFAULT_THRESHOLD_IN_SECS);
    }

    protected function check(InputInterface $input, OutputInterface $output): array
    {
        $threshold = $this->getIntOption($input, self::OPTION_THRESHOLD, 1);

        $statement = $this->createConnection()->prepare(
            'SELECT job_code, COUNT(*) AS runs, MAX(TIMESTAMPDIFF(SECOND, executed_at, finished_at)) AS max_duration
            FROM cron_schedule
            WHERE executed_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY
                AND finished_at IS NOT NULL
                AND TIMESTAMPDIFF(SECOND, executed_at, finished_at) > :threshold
            GROUP BY job_code
            ORDER BY max_duration DESC'
        );
        $statement->bindValue('threshold', $threshold, \PDO::PARAM_INT);
        $statement->execute();
        $jobs = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $output->writeln(sprintf('Magento cron jobs running longer than %d sec in the last 24h: %d', $threshold, count($jobs)));

        $messages = [];
        foreach ($jobs as $job) {
            $messages[] = sprintf(
                'Magento cron job %s ran longer than %d sec %d time(s), max %d sec, for %s',
                $job['job_code'],
                $threshold,
                $job['runs'],
                $job['max_duration'],
                $_ENV['YOUTRACK_PROJECT_CODE']
            );
        }

        return $messages;
    }

    /**
     * Connects to DB_NAME using the [client] credentials from ~/.my.cnf.
     */
    private function createConnection(): \PDO
    {
        $dbName = trim((string)($_ENV['DB_NAME'] ?? ''));
        if ($dbName === '') {
            throw new \LogicException('Please configure DB_NAME in your .env file');
        }

        $client = $this->getMysqlClientConfig();
        $dsn = isset($client['socket'])
            ? "mysql:unix_socket={$client['socket']};dbname={$dbName};charset=utf8mb4"
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $client['host'] ?? 'localhost', $client['port'] ?? 3306, $dbName);

        return new \PDO($dsn, $client['user'] ?? null, $client['password'] ?? null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * @return array<string, string>
     */
    private function getMysqlClientConfig(): array
    {
        $home = rtrim((string)($_SERVER['HOME'] ?? getenv('HOME')), '/');
        $configFile = $home . '/' . self::MYSQL_CONFIG_FILE;
        if ($home === '' || !is_readable($configFile)) {
            throw new \LogicException("MySQL config file '{$configFile}' does not exist or is not readable");
        }

        $config = parse_ini_file($configFile, true, INI_SCANNER_RAW);
        if (!is_array($config) || !isset($config['client'])) {
            throw new \LogicException("MySQL config file '{$configFile}' has no [client] section");
        }

        return array_map(static fn ($value) => trim((string)$value, " \t\"'"), $config['client']);
    }
}
