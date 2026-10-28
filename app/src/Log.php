<?php
declare(strict_types=1);

namespace Tms;

final class Log
{
    public static function write(string $channel, string $line): void
    {
        $file = TMS_STORAGE . '/logs/' . $channel . '-' . gmdate('Y-m') . '.log';
        @file_put_contents($file, gmdate('c') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
