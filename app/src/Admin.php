<?php
declare(strict_types=1);

namespace Tms;

/**
 * The owner's panel at /admin: counts, mail domains and logs.
 *
 * The password hash lives in storage/admin.php. Deleting that file (cPanel File Manager)
 * resets the password. Setting a password the first time needs the code in
 * storage/admin-setup-code.txt, so a stranger who finds /admin first cannot claim it.
 */
final class Admin
{
    private const COOKIE = 'tms_admin';
    private const SESSION_TTL = 7 * 86400;
    private const TABS = ['dashboard', 'domains', 'mail', 'logs'];

    public static function handle(string $method, string $path): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, no-store');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'; form-action 'self'");

        if ($path !== '/admin') {
            header('Location: /admin');
            http_response_code(302);
            return;
        }

        $hash = self::passwordHash();
        $flash = null;
        $error = null;

        if ($hash === null) {
            if ($method === 'POST') {
                $error = self::setup();
                if ($error === null) {
                    self::redirect('dashboard', 'Password saved. Welcome to your admin panel.');
                    return;
                }
            }
            self::setupCode();
            self::page('setup', ['error' => $error]);
            return;
        }

        if (!self::loggedIn($hash)) {
            if ($method === 'POST' && ($_POST['action'] ?? '') === 'login') {
                $error = self::login($hash);
                if ($error === null) {
                    self::redirect('dashboard');
                    return;
                }
            }
            self::page('login', ['error' => $error]);
            return;
        }

        if ($method === 'POST') {
            if (!hash_equals(self::csrf(), (string) ($_POST['csrf'] ?? ''))) {
                http_response_code(400);
                self::page('login', ['error' => 'Your session expired. Log in again.']);
                return;
            }
            [$tab, $flash, $error] = self::act((string) ($_POST['action'] ?? ''));
            if ($error === null) {
                self::redirect($tab, $flash);
                return;
            }
            $_GET['tab'] = $tab;
        }

        $tab = in_array($_GET['tab'] ?? '', self::TABS, true) ? $_GET['tab'] : 'dashboard';
        $flash = $flash ?? (isset($_GET['ok']) ? substr((string) $_GET['ok'], 0, 200) : null);
        self::page($tab, ['error' => $error, 'flash' => $flash] + self::data($tab));
    }

    // ---- auth -------------------------------------------------------------

    private static function passwordFile(): string
    {
        return TMS_STORAGE . '/admin.php';
    }

    private static function setupCodeFile(): string
    {
        return TMS_STORAGE . '/admin-setup-code.txt';
    }

    private static function passwordHash(): ?string
    {
        $file = self::passwordFile();
        if (!is_file($file)) {
            return null;
        }
        $data = require $file;
        return is_array($data) && !empty($data['hash']) ? (string) $data['hash'] : null;
    }

    private static function savePassword(string $password): void
    {
        $php = "<?php\n// Temp Mail Site admin password. Delete this file to set a new password at /admin.\nreturn "
            . var_export(['hash' => password_hash($password, PASSWORD_DEFAULT), 'set_at' => time()], true) . ";\n";
        file_put_contents(self::passwordFile(), $php, LOCK_EX);
        @chmod(self::passwordFile(), 0600);
    }

    private static function setupCode(): string
    {
        $file = self::setupCodeFile();
        $code = is_file($file) ? trim((string) file_get_contents($file)) : '';
        if (!preg_match('/^[A-Z0-9]{8}$/', $code)) {
            $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));
            file_put_contents($file, $code . "\n", LOCK_EX);
            @chmod($file, 0600);
        }
        return $code;
    }

    private static function setup(): ?string
    {
        if (!RateLimit::hit('admin-login:' . Http::ip(), 10, 900)) {
            return 'Too many tries. Wait 15 minutes.';
        }
        $code = self::setupCode();
        if (!hash_equals($code, strtoupper(trim((string) ($_POST['code'] ?? ''))))) {
            return 'That setup code is wrong. Open storage/admin-setup-code.txt in cPanel File Manager and copy the code.';
        }
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            return 'Use at least 8 characters for the password.';
        }
        if ($password !== (string) ($_POST['password2'] ?? '')) {
            return 'The two passwords do not match.';
        }
        self::savePassword($password);
        @unlink(self::setupCodeFile());
        Log::write('admin', 'password set from ' . Http::ip());
        self::startSession((string) self::passwordHash());
        return null;
    }

    private static function login(string $hash): ?string
    {
        if (!RateLimit::hit('admin-login:' . Http::ip(), 10, 900)) {
            return 'Too many tries. Wait 15 minutes.';
        }
        if (!password_verify((string) ($_POST['password'] ?? ''), $hash)) {
            Log::write('admin', 'failed login from ' . Http::ip());
            return 'Wrong password.';
        }
        self::startSession($hash);
        return null;
    }

    private static function sign(int $expires, string $hash): string
    {
        // Tied to the password hash, so changing the password logs out every other session.
        return hash_hmac('sha256', 'admin|' . $expires . '|' . $hash, (string) Config::get('app_key'));
    }

    private static function startSession(string $hash): void
    {
        $expires = time() + self::SESSION_TTL;
        $value = $expires . '.' . self::sign($expires, $hash);
        setcookie(self::COOKIE, $value, [
            'expires' => $expires, 'path' => '/admin', 'secure' => Http::isHttps(), 'httponly' => true, 'samesite' => 'Strict',
        ]);
        $_COOKIE[self::COOKIE] = $value;
    }

    private static function loggedIn(string $hash): bool
    {
        $parts = explode('.', (string) ($_COOKIE[self::COOKIE] ?? ''), 2);
        if (count($parts) !== 2 || (int) $parts[0] < time()) {
            return false;
        }
        return hash_equals(self::sign((int) $parts[0], $hash), $parts[1]);
    }

    private static function csrf(): string
    {
        return hash_hmac('sha256', 'csrf|' . ($_COOKIE[self::COOKIE] ?? ''), (string) Config::get('app_key'));
    }

    private static function redirect(string $tab, ?string $flash = null): void
    {
        $query = ['tab' => $tab];
        if ($flash !== null) {
            $query['ok'] = $flash;
        }
        header('Location: /admin?' . http_build_query($query));
        http_response_code(303);
    }

    // ---- actions ----------------------------------------------------------

    /** @return array{0: string, 1: ?string, 2: ?string} tab, success message, error */
    private static function act(string $action): array
    {
        $domain = strtolower(trim((string) ($_POST['domain'] ?? '')));
        switch ($action) {
            case 'logout':
                setcookie(self::COOKIE, '', ['expires' => 1, 'path' => '/admin', 'secure' => Http::isHttps(), 'httponly' => true, 'samesite' => 'Strict']);
                return ['dashboard', 'Logged out.', null];

            case 'add-domain':
                $domain = preg_replace('#^(https?://)?(www\.)?#', '', $domain);
                $domain = rtrim((string) $domain, '/');
                if (!preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
                    return ['domains', null, 'That does not look like a domain. Type it like example.com'];
                }
                if (Db::one('SELECT 1 FROM domains WHERE name = ?', [$domain])) {
                    return ['domains', null, $domain . ' is already in the list.'];
                }
                $pos = (int) Db::one('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM domains')['p'];
                Db::run('INSERT INTO domains (name, active, position, added_at) VALUES (?, 1, ?, ?)', [$domain, $pos, time()]);
                Log::write('admin', 'added domain ' . $domain);
                return ['domains', $domain . ' added. Now point its mail here (steps below).', null];

            case 'toggle-domain':
                $row = Db::one('SELECT active FROM domains WHERE name = ?', [$domain]);
                if (!$row) {
                    return ['domains', null, 'Domain not found.'];
                }
                if ($row['active'] && count(Mailbox::domains()) <= 1) {
                    return ['domains', null, 'Keep at least one domain switched on.'];
                }
                Db::run('UPDATE domains SET active = ? WHERE name = ?', [$row['active'] ? 0 : 1, $domain]);
                Log::write('admin', ($row['active'] ? 'paused' : 'resumed') . ' domain ' . $domain);
                return ['domains', $domain . ($row['active'] ? ' paused: no new addresses on it. Old ones still get mail.' : ' is live again.'), null];

            case 'default-domain':
                if (!Db::one('SELECT 1 FROM domains WHERE name = ?', [$domain])) {
                    return ['domains', null, 'Domain not found.'];
                }
                $min = (int) Db::one('SELECT MIN(position) AS p FROM domains')['p'];
                Db::run('UPDATE domains SET position = ?, active = 1 WHERE name = ?', [$min - 1, $domain]);
                Log::write('admin', 'default domain ' . $domain);
                return ['domains', $domain . ' is now the default for new addresses.', null];

            case 'remove-domain':
                $live = (int) Db::one('SELECT COUNT(*) AS n FROM mailboxes WHERE address LIKE ? AND expires_at > ?', ['%@' . $domain, time()])['n'];
                if ($live > 0) {
                    return ['domains', null, $domain . ' still has ' . $live . ' live addresses. Pause it instead; remove it after they expire.'];
                }
                if (Db::one('SELECT active FROM domains WHERE name = ?', [$domain])['active'] ?? 0) {
                    if (count(Mailbox::domains()) <= 1) {
                        return ['domains', null, 'Keep at least one domain switched on.'];
                    }
                }
                Db::run('DELETE FROM domains WHERE name = ?', [$domain]);
                Log::write('admin', 'removed domain ' . $domain);
                return ['domains', $domain . ' removed.', null];

            case 'change-password':
                if (!password_verify((string) ($_POST['current'] ?? ''), (string) self::passwordHash())) {
                    return ['dashboard', null, 'Current password is wrong.'];
                }
                $password = (string) ($_POST['password'] ?? '');
                if (strlen($password) < 8 || $password !== (string) ($_POST['password2'] ?? '')) {
                    return ['dashboard', null, 'New password needs 8+ characters and both boxes must match.'];
                }
                self::savePassword($password);
                self::startSession((string) self::passwordHash());
                Log::write('admin', 'password changed from ' . Http::ip());
                return ['dashboard', 'Password changed.', null];
        }
        return ['dashboard', null, 'Unknown action.'];
    }

    // ---- data -------------------------------------------------------------

    private static function data(string $tab): array
    {
        $now = time();
        if ($tab === 'dashboard') {
            $counts = [];
            foreach (['mailboxes', 'emails', 'dropped'] as $metric) {
                $counts[$metric] = [
                    'today' => Stats::sum($metric, 1), 'week' => Stats::sum($metric, 7),
                    'month' => Stats::sum($metric, 30), 'total' => Stats::sum($metric),
                ];
            }
            $cron = Db::setting('cron_last_run');
            return [
                'counts' => $counts,
                'daily' => Stats::daily(14),
                'live' => [
                    'mailboxes' => (int) Db::one('SELECT COUNT(*) AS n FROM mailboxes WHERE expires_at > ?', [$now])['n'],
                    'emails' => (int) Db::one('SELECT COUNT(*) AS n FROM messages')['n'],
                    'accounts' => (int) Db::one('SELECT COUNT(*) AS n FROM accounts')['n'],
                    'online' => (int) Db::one('SELECT COUNT(*) AS n FROM accounts WHERE last_seen_at > ?', [$now - 600])['n'],
                ],
                'speed' => Db::one(
                    'SELECT COUNT(*) AS n, AVG(received_at - sent_at) AS total, AVG(received_at - server_at) AS ours
                     FROM messages WHERE sent_at IS NOT NULL AND server_at IS NOT NULL AND received_at > ?',
                    [$now - 86400]
                ),
                'cron' => $cron !== null ? (int) $cron : null,
                'disk' => self::storageSize(),
            ];
        }
        if ($tab === 'domains') {
            $domains = Db::all('SELECT * FROM domains ORDER BY position, added_at');
            foreach ($domains as &$d) {
                $d['live'] = (int) Db::one('SELECT COUNT(*) AS n FROM mailboxes WHERE address LIKE ? AND expires_at > ?', ['%@' . $d['name'], $now])['n'];
                $d['mx'] = self::mx((string) $d['name']);
            }
            unset($d);
            return [
                'domains' => $domains,
                'default' => Mailbox::domains()[0] ?? '',
                'secret' => (string) Config::get('ingest_secret'),
                'siteUrl' => rtrim((string) Config::get('site_url'), '/'),
                'pipe' => TMS_ROOT . '/bin/pipe.php',
            ];
        }
        if ($tab === 'mail') {
            return [
                'emails' => Db::all(
                    'SELECT msg.id, msg.from_name, msg.from_addr, msg.subject, msg.otp, msg.size, msg.received_at, msg.sent_at, msg.via, msg.trace, box.address
                     FROM messages msg JOIN mailboxes box ON box.id = msg.mailbox_id ORDER BY msg.received_at DESC LIMIT 50'
                ),
                'boxes' => Db::all(
                    'SELECT m.address, m.created_at, m.expires_at, (SELECT COUNT(*) FROM messages WHERE mailbox_id = m.id) AS emails
                     FROM mailboxes m ORDER BY m.created_at DESC LIMIT 50'
                ),
            ];
        }
        return ['logs' => self::logs()];
    }

    /** @return array{0: string[], 1: bool} MX hosts, and whether DNS could be read */
    private static function mx(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_MX);
        if ($records === false || (!$records && !@dns_get_record($domain, DNS_NS))) {
            return [[], false]; // DNS lookup failed or the domain does not exist yet
        }
        usort($records, fn ($a, $b) => ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0));
        return [array_map(fn ($r) => (string) ($r['target'] ?? ''), $records), true];
    }

    /** Last lines of every log file from this month and last month, newest first. */
    private static function logs(): array
    {
        $out = [];
        $months = [gmdate('Y-m'), gmdate('Y-m', strtotime('first day of last month'))];
        foreach (['error', 'ingest', 'cron', 'admin'] as $channel) {
            $lines = [];
            foreach ($months as $month) {
                $file = TMS_STORAGE . '/logs/' . $channel . '-' . $month . '.log';
                if (is_file($file)) {
                    $lines = array_merge($lines, array_reverse(self::tail($file, 100)));
                }
                if (count($lines) >= 100) {
                    break;
                }
            }
            $out[$channel] = array_slice($lines, 0, 100);
        }
        return $out;
    }

    private static function tail(string $file, int $lines): array
    {
        $size = filesize($file);
        $fh = fopen($file, 'rb');
        if (!$fh || !$size) {
            return [];
        }
        $chunk = min($size, 64 * 1024);
        fseek($fh, -$chunk, SEEK_END);
        $data = (string) fread($fh, $chunk);
        fclose($fh);
        $all = preg_split('/\R/', trim($data));
        if ($chunk < $size) {
            array_shift($all); // first line may be cut
        }
        return array_slice($all, -$lines);
    }

    private static function storageSize(): int
    {
        $bytes = 0;
        foreach (['/tempmail.sqlite', '/tempmail.sqlite-wal'] as $f) {
            $bytes += is_file(TMS_STORAGE . $f) ? (int) filesize(TMS_STORAGE . $f) : 0;
        }
        $bytes += (int) Db::one('SELECT COALESCE(SUM(size), 0) AS n FROM attachments')['n'];
        return $bytes;
    }

    private static function page(string $tab, array $vars): void
    {
        $vars['tab'] = $tab;
        $vars['site'] = (string) Config::get('site_name');
        $vars['csrf'] = in_array($tab, self::TABS, true) ? self::csrf() : '';
        $vars['tz'] = new \DateTimeZone((string) (Config::get('timezone') ?: 'UTC'));
        extract($vars, EXTR_SKIP);
        $e = [Http::class, 'e'];
        header('Content-Type: text/html; charset=utf-8');
        require TMS_ROOT . '/app/views/admin.php';
    }
}
