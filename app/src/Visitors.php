<?php
declare(strict_types=1);

namespace Tms;

/**
 * Visitor log for /admin → Visitors. One row per visit (a visit ends after 30 idle minutes).
 * Rows older than 30 days are deleted by Cleanup. Stores IP, browser, device, country (Cloudflare),
 * where the visitor came from, time on site and how many addresses they made.
 */
final class Visitors
{
    private const COOKIE = 'tms_vid';
    private const IDLE = 1800;
    public const KEEP_DAYS = 30;

    public static function isBot(string $ua): bool
    {
        return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|bing|yandex|baidu|duckduck|facebookexternalhit|preview|monitor|uptime|curl|wget|python|java\/|go-http|headless|lighthouse|pingdom|semrush|ahrefs|mj12|dotbot|petalbot/i', $ua);
    }

    /** @return array{0: string, 1: string, 2: string} browser name, version, device */
    public static function parseUa(string $ua): array
    {
        $device = preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua) || (preg_match('/Android/i', $ua) && !preg_match('/Mobile/i', $ua)) ? 'Tablet'
            : (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone/i', $ua) ? 'Mobile' : 'Desktop');
        $map = [
            ['Edge', '/Edg(?:e|A|iOS)?\/([\d.]+)/'], ['Opera', '/(?:OPR|Opera)\/([\d.]+)/'], ['Samsung Browser', '/SamsungBrowser\/([\d.]+)/'],
            ['Facebook app', '/FB(?:AV|AN)\/([\d.]+)/'], ['Instagram app', '/Instagram ([\d.]+)/'], ['UC Browser', '/UCBrowser\/([\d.]+)/'],
            ['Firefox', '/(?:Firefox|FxiOS)\/([\d.]+)/'], ['Chrome', '/(?:Chrome|CriOS)\/([\d.]+)/'], ['Safari', '/Version\/([\d.]+).*Safari/'],
            ['Internet Explorer', '/(?:MSIE |Trident\/.*rv:)([\d.]+)/'],
        ];
        foreach ($map as [$name, $re]) {
            if (preg_match($re, $ua, $m)) {
                return [$name, explode('.', $m[1])[0], $device];
            }
        }
        return ['Other', '', $device];
    }

    private static function cookieId(): string
    {
        $v = (string) ($_COOKIE[self::COOKIE] ?? '');
        return preg_match('/^[a-f0-9]{24}$/', $v) ? $v : '';
    }

    /** Call on every public page view. Never throws. */
    public static function track(): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
                return;
            }
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            if (self::isBot($ua)) {
                return;
            }
            $now = time();
            $vid = self::cookieId();
            if ($vid !== '' && Db::run('UPDATE visits SET pages = pages + 1, last_at = ? WHERE vid = ? AND last_at > ?', [$now, $vid, $now - self::IDLE]) > 0) {
                return;
            }
            $vid = bin2hex(random_bytes(12));
            [$browser, $ver, $device] = self::parseUa($ua);
            $cc = strtoupper((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
            $cc = preg_match('/^[A-Z]{2}$/', $cc) && !in_array($cc, ['XX', 'T1'], true) ? $cc : '';
            $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
            $own = strtolower((string) parse_url((string) Config::get('site_url'), PHP_URL_HOST));
            $strip = fn (string $h) => preg_replace('/^www\./', '', $h);
            if ($host === '' || $strip($host) === $strip($own) || !preg_match('#^https?://#i', $ref)) {
                $ref = '';
            }
            Db::run(
                'INSERT INTO visits (vid, ip, browser, bver, device, country, ref, landing, first_at, last_at, pages, mails) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)',
                [$vid, Http::ip(), $browser, $ver, $device, $cc, mb_substr($ref, 0, 300), mb_substr('/' . trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/'), 0, 200), $now, $now]
            );
            setcookie(self::COOKIE, $vid, ['path' => '/', 'secure' => Http::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
            $_COOKIE[self::COOKIE] = $vid;
        } catch (\Throwable $e) {
            Log::write('error', 'visitors track: ' . $e->getMessage());
        }
    }

    /** Heartbeat from the page every ~25 seconds while it is open. */
    public static function ping(): void
    {
        $vid = self::cookieId();
        if ($vid !== '') {
            $now = time();
            Db::run('UPDATE visits SET last_at = ? WHERE vid = ? AND last_at > ?', [$now, $vid, $now - self::IDLE]);
        }
    }

    public static function bumpMail(): void
    {
        try {
            $vid = self::cookieId();
            if ($vid !== '') {
                Db::run('UPDATE visits SET mails = mails + 1, last_at = ? WHERE vid = ?', [time(), $vid]);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function online(): int
    {
        return (int) Db::one('SELECT COUNT(*) AS n FROM visits WHERE last_at > ?', [time() - 70])['n'];
    }

    public static function purge(): int
    {
        return Db::run('DELETE FROM visits WHERE last_at < ?', [time() - self::KEEP_DAYS * 86400]);
    }
}
