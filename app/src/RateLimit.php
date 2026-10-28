<?php
declare(strict_types=1);

namespace Tms;

final class RateLimit
{
    /** Counts one hit; returns false once the bucket is over its limit for the window. */
    public static function hit(string $bucket, int $limit, int $window): bool
    {
        $key = hash('sha256', $bucket . '|' . Config::get('app_key'));
        $now = time();
        $row = Db::one('SELECT hits, expires_at FROM rate_limits WHERE bucket = ?', [$key]);
        if (!$row || (int) $row['expires_at'] < $now) {
            Db::run('INSERT OR REPLACE INTO rate_limits (bucket, hits, expires_at) VALUES (?, 1, ?)', [$key, $now + $window]);
            return true;
        }
        if ((int) $row['hits'] >= $limit) {
            return false;
        }
        Db::run('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?', [$key]);
        return true;
    }
}
