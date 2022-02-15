<?php

namespace ServerMonitor\Utility;

class Util
{
    public static function stringContains(string $subject, string $find): bool
    {
        return (strpos($subject, $find) !== false);
    }
}
