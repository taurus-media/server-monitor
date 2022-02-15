<?php

namespace LockCollector\Utility;

class Util
{
    public static function stringContains(string $subject, string $find)
    {
        return (strpos($subject, $find) !== false);
    }
}
