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
    private const TABS = ['dashboard', 'blog', 'pages', 'faq', 'messages', 'settings', 'domains', 'mail', 'logs'];

    public static function handle(string $method, string $path): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, no-store');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; frame-ancestors 'none'; form-action 'self'");

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
            if (($_POST['action'] ?? '') === 'upload-image') {
                try {
                    Http::json(['url' => Content::saveImage($_FILES['image'] ?? [])]);
                } catch (UserError $e) {
                    Http::json(['error' => $e->getMessage()], 400);
                }
                return;
            }
            try {
                $result = self::act((string) ($_POST['action'] ?? ''));
            } catch (UserError $e) {
                $result = [(string) ($_POST['tab'] ?? 'dashboard'), null, $e->getMessage()];
            }
            [$tab, $flash, $error] = $result;
            $extra = $result[3] ?? [];
            if ($error === null) {
                self::redirect($tab, $flash, $extra);
                return;
            }
            $_GET['tab'] = $tab;
            $_GET += $extra;
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
        $php = "<?php\n// Bengal Email admin password. Delete this file to set a new password at /admin.\nreturn "
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

    private static function redirect(string $tab, ?string $flash = null, array $extra = []): void
    {
        $query = ['tab' => $tab] + $extra;
        if ($flash !== null) {
            $query['ok'] = $flash;
        }
        header('Location: /admin?' . http_build_query($query));
        http_response_code(303);
    }

    // ---- actions ----------------------------------------------------------

    /** @return array{0: string, 1: ?string, 2: ?string, 3?: array} tab, success message, error, extra query */
    private static function act(string $action): array
    {
        $domain = strtolower(trim((string) ($_POST['domain'] ?? '')));
        $content = self::actContent($action);
        if ($content !== null) {
            return $content;
        }
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

    /** Blog, pages, FAQ, messages and settings. Returns null for actions handled elsewhere. */
    private static function actContent(string $action): ?array
    {
        $now = time();
        $str = fn (string $k, int $max = 100000) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        switch ($action) {
            case 'save-post':
                $id = (int) ($_POST['id'] ?? 0);
                $title = $str('title', 200);
                if ($title === '') {
                    return ['blog', null, 'Give the post a title.', ['edit' => $id ?: 'new']];
                }
                $slug = Content::slugify($str('slug', 120) ?: $title);
                $clash = Db::one('SELECT id FROM posts WHERE slug = ? AND id != ?', [$slug, $id]);
                if ($clash) {
                    return ['blog', null, 'Another post already uses the link /blog/' . $slug . '. Change the link (slug).', ['edit' => $id ?: 'new']];
                }
                $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
                $tz = new \DateTimeZone((string) (Config::get('timezone') ?: 'UTC'));
                $date = $str('date', 40);
                try {
                    $at = $date !== '' ? (new \DateTimeImmutable($date, $tz))->getTimestamp() : $now;
                } catch (\Exception $e) {
                    $at = $now;
                }
                $cover = $str('cover', 500);
                if (!empty($_POST['remove_cover'])) {
                    $cover = '';
                }
                if (($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $cover = Content::saveImage($_FILES['cover_file']);
                }
                $values = [$slug, $title, $str('description', 300), (string) ($_POST['body'] ?? ''), $cover, $status, $at, $now];
                if ($id > 0 && Db::one('SELECT 1 FROM posts WHERE id = ?', [$id])) {
                    Db::run('UPDATE posts SET slug = ?, title = ?, description = ?, body = ?, cover = ?, status = ?, published_at = ?, updated_at = ? WHERE id = ?', [...$values, $id]);
                } else {
                    $id = Db::insert('INSERT INTO posts (slug, title, description, body, cover, status, published_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $values);
                }
                Log::write('admin', 'saved post ' . $slug . ' (' . $status . ')');
                $msg = $status === 'published' ? 'Post saved and published.' : 'Post saved as a draft (not visible on the site).';
                return ['blog', $msg, null, ['edit' => $id]];

            case 'delete-post':
                $row = Db::one('SELECT slug FROM posts WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
                if ($row) {
                    Db::run('DELETE FROM posts WHERE id = ?', [(int) $_POST['id']]);
                    Log::write('admin', 'deleted post ' . $row['slug']);
                }
                return ['blog', 'Post deleted.', null];

            case 'save-page':
                $slug = (string) ($_POST['slug'] ?? '');
                if (!isset(Content::PAGES[$slug])) {
                    return ['pages', null, 'Page not found.'];
                }
                $title = $str('title', 200) ?: Content::PAGES[$slug][0];
                Db::run('INSERT OR REPLACE INTO pages (slug, title, description, body, updated_at) VALUES (?, ?, ?, ?, ?)',
                    [$slug, $title, $str('description', 300), (string) ($_POST['body'] ?? ''), $now]);
                Log::write('admin', 'saved page ' . $slug);
                return ['pages', 'Page saved. It is live now.', null, ['edit' => $slug]];

            case 'reset-page':
                $slug = (string) ($_POST['slug'] ?? '');
                Db::run('DELETE FROM pages WHERE slug = ?', [$slug]);
                Log::write('admin', 'reset page ' . $slug);
                return ['pages', 'Page put back to the original text.', null, ['edit' => $slug]];

            case 'add-faq':
            case 'save-faq':
                $q = $str('question', 500);
                $a = $str('answer', 5000);
                if ($q === '' || $a === '') {
                    return ['faq', null, 'Write both the question and the answer.'];
                }
                $pos = $_POST['position'] ?? '';
                if ($action === 'add-faq') {
                    $pos = $pos === '' ? (int) Db::one('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM faqs')['p'] : (int) $pos;
                    Db::insert('INSERT INTO faqs (question, answer, position) VALUES (?, ?, ?)', [$q, $a, $pos]);
                    return ['faq', 'Question added.', null];
                }
                Db::run('UPDATE faqs SET question = ?, answer = ?, position = ? WHERE id = ?', [$q, $a, (int) $pos, (int) ($_POST['id'] ?? 0)]);
                return ['faq', 'Question saved.', null];

            case 'delete-faq':
                Db::run('DELETE FROM faqs WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
                return ['faq', 'Question deleted.', null];

            case 'delete-message':
                Db::run('DELETE FROM contact_messages WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
                return ['messages', 'Message deleted.', null];

            case 'read-messages':
                Db::run('UPDATE contact_messages SET seen = 1');
                return ['messages', 'All messages marked as read.', null];

            case 'save-settings':
                foreach (array_keys(Content::SETTINGS) as $key) {
                    $value = $str($key, 1000);
                    if (str_starts_with($key, 'social_') && $value !== '' && !preg_match('#^https?://#i', $value)) {
                        return ['settings', null, 'Links must start with https:// (check ' . Content::SETTINGS[$key][0] . ').'];
                    }
                    Db::setSetting('content_' . $key, $value);
                }
                Log::write('admin', 'saved site settings');
                return ['settings', 'Settings saved.', null];
        }
        return null;
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
        if ($tab === 'blog') {
            $edit = (string) ($_GET['edit'] ?? '');
            $post = null;
            if ($edit === 'new') {
                $post = ['id' => 0, 'slug' => '', 'title' => '', 'description' => '', 'body' => '', 'cover' => '', 'status' => 'draft', 'published_at' => $now];
            } elseif ($edit !== '') {
                $post = Db::one('SELECT * FROM posts WHERE id = ?', [(int) $edit]);
            }
            if ($post !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // Keep what was typed when saving failed.
                foreach (['title', 'slug', 'description', 'body', 'cover', 'status'] as $k) {
                    $post[$k] = (string) ($_POST[$k] ?? $post[$k]);
                }
            }
            return [
                'post' => $post,
                'posts' => $post === null ? Db::all('SELECT id, slug, title, status, published_at, updated_at FROM posts ORDER BY published_at DESC') : [],
            ];
        }
        if ($tab === 'pages') {
            $edit = (string) ($_GET['edit'] ?? '');
            $page = isset(Content::PAGES[$edit]) ? Content::page($edit) + ['slug' => $edit] : null;
            if ($page !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
                foreach (['title', 'description', 'body'] as $k) {
                    $page[$k] = (string) ($_POST[$k] ?? $page[$k]);
                }
            }
            $custom = array_column(Db::all('SELECT slug, updated_at FROM pages'), 'updated_at', 'slug');
            return ['page' => $page, 'custom' => $custom];
        }
        if ($tab === 'faq') {
            return ['faqs' => Content::faqs()];
        }
        if ($tab === 'messages') {
            return ['messages' => Db::all('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 200')];
        }
        if ($tab === 'settings') {
            return ['settings' => Content::settings()];
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
            // Everything ever received and every address ever made, kept even after visitors delete them.
            $q = strtolower(trim((string) ($_GET['q'] ?? '')));
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $per = 50;
            $mp = max(1, (int) ($_GET['mp'] ?? 1));
            $bp = max(1, (int) ($_GET['bp'] ?? 1));
            $mailWhere = $q !== '' ? "WHERE l.address LIKE ? ESCAPE '\\' OR l.from_addr LIKE ? ESCAPE '\\' OR l.subject LIKE ? ESCAPE '\\'" : '';
            $mailArgs = $q !== '' ? [$like, $like, $like] : [];
            $boxWhere = $q !== '' ? "WHERE b.address LIKE ? ESCAPE '\\'" : '';
            $boxArgs = $q !== '' ? [$like] : [];
            return [
                'q' => $q, 'mp' => $mp, 'bp' => $bp, 'per' => $per,
                'emailTotal' => (int) Db::one('SELECT COUNT(*) AS n FROM mail_log l ' . $mailWhere, $mailArgs)['n'],
                'emails' => Db::all(
                    'SELECT l.*, EXISTS (SELECT 1 FROM messages WHERE id = l.message_id) AS in_inbox
                     FROM mail_log l ' . $mailWhere . ' ORDER BY l.received_at DESC, l.id DESC LIMIT ' . $per . ' OFFSET ' . (($mp - 1) * $per),
                    $mailArgs
                ),
                'boxTotal' => (int) Db::one('SELECT COUNT(*) AS n FROM mailbox_log b ' . $boxWhere, $boxArgs)['n'],
                'boxes' => Db::all(
                    'SELECT b.*, (SELECT expires_at FROM mailboxes WHERE address = b.address) AS expires_at,
                        (SELECT COUNT(*) FROM mail_log l WHERE l.address = b.address AND l.received_at >= b.created_at AND l.received_at <= COALESCE(b.ended_at, ?)) AS emails
                     FROM mailbox_log b ' . $boxWhere . ' ORDER BY b.created_at DESC, b.id DESC LIMIT ' . $per . ' OFFSET ' . (($bp - 1) * $per),
                    array_merge([$now], $boxArgs)
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
        $vars['unread'] = in_array($tab, self::TABS, true) ? (int) Db::one('SELECT COUNT(*) AS n FROM contact_messages WHERE seen = 0')['n'] : 0;
        extract($vars, EXTR_SKIP);
        $e = [Http::class, 'e'];
        header('Content-Type: text/html; charset=utf-8');
        require TMS_ROOT . '/app/views/admin.php';
    }
}
