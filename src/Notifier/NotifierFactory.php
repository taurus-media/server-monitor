<?php declare(strict_types=1);

namespace ServerMonitor\Notifier;

class NotifierFactory
{
    public const NOTIFIER_SLACK = 'slack';
    public const NOTIFIER_SENTRY = 'sentry';

    public function create(): NotifierInterface
    {
        $notifier = strtolower($this->getEnv('NOTIFIER', self::NOTIFIER_SLACK));

        return match ($notifier) {
            self::NOTIFIER_SLACK => $this->createSlackNotifier(),
            self::NOTIFIER_SENTRY => new SentryNotifier(
                $this->getEnv('SENTRY_DSN'),
                $this->getEnv('YOUTRACK_PROJECT_CODE')
            ),
            default => throw new \LogicException(
                "Unknown NOTIFIER '{$notifier}', supported values: " . self::NOTIFIER_SLACK . ', ' . self::NOTIFIER_SENTRY
            ),
        };
    }

    private function createSlackNotifier(): SlackNotifier
    {
        return new SlackNotifier(
            $this->getEnv('SLACK_WEBHOOK_URL'),
            $this->getEnv('YOUTRACK_PROJECT_CODE')
        );
    }

    private function getEnv(string $name, string $default = ''): string
    {
        $value = trim((string)($_ENV[$name] ?? ''));
        return $value !== '' ? $value : $default;
    }
}
