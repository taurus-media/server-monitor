<?php declare(strict_types=1);

namespace ServerMonitor\Notifier;

interface NotifierInterface
{
    /**
     * @param string[] $messages
     */
    public function notify(string $commandName, array $messages): void;
}
