<?php declare(strict_types=1);

namespace ServerMonitor\Notifier;

/**
 * Posts to a Slack channel through an incoming webhook.
 */
class SlackNotifier implements NotifierInterface
{
    private const TIMEOUT = 10;

    public function __construct(
        private readonly string $webhookUrl,
        private readonly string $projectCode
    ) {
        if (!str_starts_with($this->webhookUrl, 'https://hooks.slack.com/')) {
            throw new \LogicException('Please provide a valid SLACK_WEBHOOK_URL in your .env file');
        }
    }

    public function notify(string $commandName, array $messages): void
    {
        $text = $this->escape("*[{$this->projectCode}] {$commandName}*") . "\n"
            . $this->escape(implode("\n", $messages));

        $ch = curl_init($this->webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['text' => $text], JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("Slack webhook request failed: {$error}");
        }
        if ($status !== 200) {
            throw new \RuntimeException("Slack webhook returned HTTP {$status}: {$response}");
        }
    }

    // Slack requires &, < and > to be escaped in message text.
    private function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}
