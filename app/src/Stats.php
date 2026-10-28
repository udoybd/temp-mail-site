<?php
declare(strict_types=1);

namespace Tms;

/** Daily counters for /admin. Rows are never cleaned up, so totals survive mail expiry. */
final class Stats
{
    public static function day(?int $ts = null): string
    {
        $tz = new \DateTimeZone((string) (Config::get('timezone') ?: 'UTC'));
        return (new \DateTimeImmutable('@' . ($ts ?? time())))->setTimezone($tz)->format('Y-m-d');
    }

    public static function bump(string $metric): void
    {
        try {
            Db::run(
                'INSERT INTO stats (day, metric, n) VALUES (?, ?, 1) ON CONFLICT(day, metric) DO UPDATE SET n = n + 1',
                [self::day(), $metric]
            );
        } catch (\Throwable $e) {
            Log::write('error', 'stats: ' . $e->getMessage());
        }
    }

    /** Sum of a metric over the last $days days including today; 0 means all time. */
    public static function sum(string $metric, int $days = 0): int
    {
        if ($days === 0) {
            return (int) Db::one('SELECT COALESCE(SUM(n), 0) AS n FROM stats WHERE metric = ?', [$metric])['n'];
        }
        return (int) Db::one(
            'SELECT COALESCE(SUM(n), 0) AS n FROM stats WHERE metric = ? AND day >= ?',
            [$metric, self::day(time() - ($days - 1) * 86400)]
        )['n'];
    }

    /** @return array<string, array<string, int>> day => metric => count, oldest first */
    public static function daily(int $days): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $out[self::day(time() - $i * 86400)] = ['mailboxes' => 0, 'emails' => 0, 'dropped' => 0];
        }
        foreach (Db::all('SELECT day, metric, n FROM stats WHERE day >= ?', [array_key_first($out)]) as $row) {
            if (isset($out[$row['day']][$row['metric']])) {
                $out[$row['day']][$row['metric']] = (int) $row['n'];
            }
        }
        return $out;
    }
}
