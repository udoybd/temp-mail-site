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

    /** Datacenter / cloud ranges: real people do not browse from these. Edit freely. */
    private const CLOUD = [
        '43.128.0.0/11', '43.160.0.0/12',            // Tencent Cloud
        '34.0.0.0/8', '35.0.0.0/8', '104.196.0.0/14', '130.211.0.0/16', '146.148.0.0/17', // Google Cloud
        '3.0.0.0/8', '65.0.0.0/14', '13.32.0.0/11', '18.128.0.0/9', '44.192.0.0/10', '52.0.0.0/10', '54.64.0.0/10', '54.144.0.0/12', '54.160.0.0/11', '54.192.0.0/10', // AWS
        '20.0.0.0/8', '40.64.0.0/10', '4.144.0.0/12',  // Microsoft Azure
        '47.52.0.0/14', '47.74.0.0/15', '47.88.0.0/13', '47.236.0.0/14', '8.208.0.0/12', // Alibaba Cloud
        '114.115.128.0/17', '119.8.0.0/16', '124.70.0.0/15', // Huawei Cloud
        '138.68.0.0/16', '139.59.0.0/16', '143.110.0.0/16', '157.230.0.0/16', '159.65.0.0/16', '159.89.0.0/16', '161.35.0.0/16', '164.90.0.0/16', '165.22.0.0/16', '167.71.0.0/16', '167.99.0.0/16', '178.128.0.0/16', '188.166.0.0/16', '206.189.0.0/16', // DigitalOcean
        '5.9.0.0/16', '78.46.0.0/15', '88.198.0.0/16', '95.216.0.0/15', '116.202.0.0/15', '135.181.0.0/16', '136.243.0.0/16', '138.201.0.0/16', '144.76.0.0/16', '148.251.0.0/16', '157.90.0.0/16', '159.69.0.0/16', '168.119.0.0/16', '178.63.0.0/16', '195.201.0.0/16', '213.239.192.0/18', // Hetzner
        '51.68.0.0/16', '51.75.0.0/16', '51.77.0.0/16', '51.83.0.0/16', '51.89.0.0/16', '54.36.0.0/16', '135.125.0.0/16', '141.94.0.0/15', '146.59.0.0/16', '147.135.0.0/16', '151.80.0.0/16', '193.70.0.0/17', // OVH
        '45.32.0.0/16', '45.63.0.0/16', '45.76.0.0/15', '66.42.0.0/16', '108.61.0.0/16', '149.28.0.0/16', '155.138.128.0/17', '207.246.64.0/18', // Vultr
        '23.92.0.0/16', '45.33.0.0/16', '45.56.0.0/16', '139.162.0.0/16', '172.104.0.0/15', '172.232.0.0/14', // Linode / Akamai
        '84.32.0.0/17', // Datacamp / CDN77 style hosting
    ];

    /** True if an IPv4 address sits inside a known cloud range. */
    public static function isCloudIp(string $ip): bool
    {
        $n = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? ip2long($ip) : false;
        if ($n === false) {
            return false;
        }
        foreach (self::CLOUD as $cidr) {
            [$base, $bits] = explode('/', $cidr);
            $mask = $bits === '0' ? 0 : (-1 << (32 - (int) $bits)) & 0xFFFFFFFF;
            if (($n & $mask) === (ip2long($base) & $mask)) {
                return true;
            }
        }
        return false;
    }

    /** Why a visit looks like a bot, or '' when it looks like a real person. */
    public static function botReason(string $ip, string $browser, string $ver, bool $noLang = false, array $cf = []): string
    {
        if (self::isCloudIp($ip)) {
            return 'Cloud/datacenter IP';
        }
        $v = (int) $ver;
        if ($browser === 'Other') {
            return 'Unknown browser';
        }
        if (($browser === 'Safari' && $v > 0 && $v <= 13) || ($browser === 'Chrome' && $v > 0 && $v < 80) || ($browser === 'Firefox' && $v > 0 && $v < 60) || $browser === 'Internet Explorer') {
            return 'Outdated fake-looking browser';
        }
        if (($cf['verified'] ?? '') !== '' && !in_array(strtolower((string) $cf['verified']), ['0', 'false'], true)) {
            return 'Cloudflare: verified bot';
        }
        if (($cf['score'] ?? '') !== '' && (int) $cf['score'] > 0 && (int) $cf['score'] < 30) {
            return 'Cloudflare: bot score ' . (int) $cf['score'];
        }
        if ($noLang) {
            return 'No browser language header';
        }
        return '';
    }

    /** SQL: a visit that never stayed 3 s, made no mail, and is over 2 minutes old (never ran the page script). */
    public static function zeroSql(): string
    {
        return '(last_at - first_at < 3 AND mails = 0 AND first_at < ' . (time() - 120) . ')';
    }

    /** Reason shown in the admin table for one visit row, or ''. */
    public static function rowReason(array $r): string
    {
        if ((string) ($r['bot'] ?? '') !== '') {
            return (string) $r['bot'];
        }
        return (int) $r['last_at'] - (int) $r['first_at'] < 3 && (int) $r['mails'] === 0 && (int) $r['first_at'] < time() - 120 ? '0s stay, no mail' : '';
    }

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
            $ip = Http::ip();
            $bot = self::botReason($ip, $browser, $ver, trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) === '', [
                'verified' => (string) ($_SERVER['HTTP_CF_VERIFIED_BOT'] ?? ''), 'score' => (string) ($_SERVER['HTTP_CF_BOT_SCORE'] ?? ''),
            ]);
            $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
            $own = strtolower((string) parse_url((string) Config::get('site_url'), PHP_URL_HOST));
            $strip = fn (string $h) => preg_replace('/^www\./', '', $h);
            if ($host === '' || $strip($host) === $strip($own) || !preg_match('#^https?://#i', $ref)) {
                $ref = '';
            }
            Db::run(
                'INSERT INTO visits (vid, ip, browser, bver, device, country, ref, landing, first_at, last_at, pages, mails, bot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?)',
                [$vid, $ip, $browser, $ver, $device, $cc, mb_substr($ref, 0, 300), mb_substr('/' . trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/'), 0, 200), $now, $now, $bot]
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
        return (int) Db::one('SELECT COUNT(*) AS n FROM visits WHERE last_at > ? AND bot = \'\'', [time() - 70])['n'];
    }

    public static function purge(): int
    {
        return Db::run('DELETE FROM visits WHERE last_at < ?', [time() - self::KEEP_DAYS * 86400]);
    }
}
