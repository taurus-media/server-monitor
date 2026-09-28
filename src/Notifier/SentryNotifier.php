<?php declare(strict_types=1);

namespace ServerMonitor\Notifier;

class SentryNotifier implements NotifierInterface
{
    public function __construct(
        private readonly string $dsn,
        private readonly string $projectCode
    ) {
        if (!str_starts_with($this->dsn, 'https://')) {
            throw new \LogicException('Please provide a valid SENTRY_DSN url in your .env file');
        }
    }

    public function notify(string $commandName, array $messages): void
    {
        \Sentry\init(['dsn' => $this->dsn]);
        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($commandName): void {
            $scope->setContext('project', [
                'YouTrack Project Code' => $this->projectCode,
            ]);
            $scope->setTag('command', $commandName);
        });

        foreach ($messages as $message) {
            \Sentry\captureMessage($message);
        }
    }
}
