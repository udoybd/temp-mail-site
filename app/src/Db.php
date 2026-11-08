<?php
declare(strict_types=1);

namespace Tms;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $pdo = new PDO('sqlite:' . TMS_STORAGE . '/tempmail.sqlite', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo = $pdo;
            self::migrate($pdo);
        }
        return self::$pdo;
    }

    public static function all(string $sql, array $args = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $args = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function run(string $sql, array $args = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st->rowCount();
    }

    public static function insert(string $sql, array $args = []): int
    {
        self::run($sql, $args);
        return (int) self::pdo()->lastInsertId();
    }

    private static function migrate(PDO $pdo): void
    {
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        if ($version < 1) {
            self::createTables($pdo);
        }
        if ($version < 2) {
            // When the sender sent it and when our mail server accepted it, to show delivery delay.
            $pdo->exec('ALTER TABLE messages ADD COLUMN sent_at INTEGER');
            $pdo->exec('ALTER TABLE messages ADD COLUMN server_at INTEGER');
            $pdo->exec('PRAGMA user_version = 2');
        }
        if ($version < 3) {
            self::addAdminTables($pdo);
        }
        if ($version < 4) {
            // How the mail reached us (pipe or cloudflare) and each server hop's time, for /admin.
            $pdo->exec('ALTER TABLE messages ADD COLUMN via TEXT');
            $pdo->exec('ALTER TABLE messages ADD COLUMN trace TEXT');
            $pdo->exec('PRAGMA user_version = 4');
        }
        if ($version < 5) {
            // Blog posts, page text, FAQ and contact messages edited from /admin.
            Content::migrate($pdo);
            $pdo->exec('PRAGMA user_version = 5');
        }
        if ($version < 6) {
            self::addHistoryTables($pdo);
        }
        if ($version < 7) {
            // Addresses now last 24 hours. Live ones keep at most 24 hours from this update.
            $pdo->prepare('UPDATE mailboxes SET expires_at = MIN(expires_at, CAST(? AS INTEGER))')->execute([time() + 86400]);
            $fix = $pdo->prepare('UPDATE faqs SET answer = ? WHERE answer = ?');
            $fix->execute(['Every address works for 24 hours from the moment it is made. Each email is deleted automatically 3 hours after it arrives. If you delete an address yourself, that name can be used again after 24 hours.', 'A guest mailbox works for 5 days. If you save a recovery code or link a second device, your mailboxes last 30 days. Each email is deleted automatically 3 hours after it arrives.']);
            $fix->execute(['Your address does not vanish after 10 minutes. It lasts a full 24 hours, you can keep several at once, open them on other devices and come back later with a recovery code.', 'Your address does not vanish after 10 minutes. Mailboxes last days, you can keep several at once, open them on other devices and come back later with a recovery code.']);
            $pdo->prepare("UPDATE posts SET body = REPLACE(body, ?, ?) WHERE slug = 'temp-mail-for-discord'")->execute(['save a recovery code under Account so the mailbox lasts 30 days, or move the account to a permanent email later in Discord settings.', 'remember that a temporary address only lasts 24 hours, so move the account to a permanent email later in Discord settings.']);
            $pdo->exec('PRAGMA user_version = 7');
        }
        if ($version < 8) {
            // Addresses deleted before the history existed: list them in /admin too.
            $pdo->exec('ALTER TABLE mailbox_log ADD COLUMN backfilled INTEGER NOT NULL DEFAULT 0');
            $pdo->exec(
                'INSERT INTO mailbox_log (address, created_at, ended_at, end_reason, backfilled)
                 SELECT r.address, r.released_at, r.released_at, r.reason, 1 FROM released_addresses r
                 WHERE NOT EXISTS (SELECT 1 FROM mailbox_log l WHERE l.address = r.address AND l.ended_at IS NOT NULL)'
            );
            // Strict 24 hours from creation for every live address.
            $pdo->exec('UPDATE mailboxes SET expires_at = created_at + 86400 WHERE expires_at > created_at + 86400');
            $pdo->exec('PRAGMA user_version = 8');
        }
        if ($version < 9) {
            // New rule: deleting frees the name at once. Explain it in the FAQ.
            $pdo->prepare('UPDATE faqs SET answer = ? WHERE answer = ?')->execute(['Every address works for 24 hours from the moment it is made. Each email is deleted automatically 3 hours after it arrives.', 'Every address works for 24 hours from the moment it is made. Each email is deleted automatically 3 hours after it arrives. If you delete an address yourself, that name can be used again after 24 hours.']);
            if (!$pdo->query("SELECT 1 FROM faqs WHERE question = 'Can I get the same address again?'")->fetchColumn()) {
                $pdo->exec('UPDATE faqs SET position = position + 1 WHERE position >= 2');
                $pdo->prepare('INSERT INTO faqs (question, answer, position) VALUES (?, ?, 2)')->execute(['Can I get the same address again?', 'Yes. If you press Delete, the address is free straight away and you can make it again at once with Change. If you do not delete it and just switch to another address (New Address or Change), it stays yours for its full 24 hours: open it again from the list under Account, or type the same name under Change. During those 24 hours nobody else can take that name; after it expires, anyone can.']);
            }
            $pdo->exec('PRAGMA user_version = 9');
        }
        if ($version < 10) {
            // Mail now stays until the owner deletes it or the address expires (24 hours). New home text.
            $old = 'Each email is deleted automatically 3 hours after it arrives.';
            $new = 'Emails stay in your inbox until you delete them or the address expires, up to 24 hours.';
            $pdo->prepare('UPDATE faqs SET answer = REPLACE(answer, ?, ?)')->execute([$old, $new]);
            $pdo->prepare('UPDATE pages SET body = REPLACE(body, ?, ?)')->execute(['Each email is deleted <strong>3 hours</strong> after it arrives.', 'Emails stay until you delete them or the address expires (up to <strong>24 hours</strong>).']);
            $pdo->prepare('UPDATE pages SET body = REPLACE(body, ?, ?)')->execute(['Emails: 3 hours after they arrive.', 'Emails: when you delete them, or when the address expires (24 hours at most).']);
            $pdo->prepare('UPDATE pages SET body = REPLACE(body, ?, ?)')->execute(['and mail is deleted after 3 hours.', 'and mail is deleted when the address expires.']);
            $pdo->prepare('UPDATE pages SET body = REPLACE(body, ?, ?)')->execute(['Emails are removed 3 hours after they arrive and addresses after 24 hours.', 'Emails and addresses are removed after 24 hours, and you can delete any email yourself.']);
            $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'content_hero_title' AND value = ?")->execute(['Free Temp Mail: Temporary Email Address in One Click', 'Free Temporary Email Address']);
            $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'content_hero_ticks' AND value = ?")->execute(['No sign-up, Lives 24 hours, Free to use', 'No sign-up, Auto-expires, Free forever']);
            $pdo->exec('PRAGMA user_version = 10');
        }
        if ($version < 11) {
            // Clearer address-reuse wording (Delete frees the name for everyone).
            $pdo->prepare('UPDATE faqs SET answer = ? WHERE question = ?')->execute(['Yes, if you do not delete it. If you only switch to another address (New Address or Change), the old one stays yours for its full 24 hours: open it again from the list under Account, or type the same name under Change. If you press Delete, the name is free straight away for everyone, so you can take it again at once, but anyone else can take it too. After 24 hours every address is free for anyone.', 'Can I get the same address again?']);
            $pdo->prepare('UPDATE pages SET body = REPLACE(body, ?, ?)')->execute(['When an address is deleted we keep the address itself (not its email) so it is never given to someone else.', 'When an address is deleted or expires, its emails are deleted too and the name becomes free for anyone to use again. We keep only a private record of the address name and its times, with no email content.']);
            $pdo->exec('PRAGMA user_version = 11');
        }
        if ($version < 12) {
            // Visitor log for /admin → Visitors (kept 30 days).
            $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vid TEXT NOT NULL,
    ip TEXT NOT NULL,
    browser TEXT NOT NULL DEFAULT '',
    bver TEXT NOT NULL DEFAULT '',
    device TEXT NOT NULL DEFAULT '',
    country TEXT NOT NULL DEFAULT '',
    ref TEXT NOT NULL DEFAULT '',
    first_at INTEGER NOT NULL,
    last_at INTEGER NOT NULL,
    pages INTEGER NOT NULL DEFAULT 1,
    mails INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS visits_vid ON visits(vid);
CREATE INDEX IF NOT EXISTS visits_first ON visits(first_at);
CREATE INDEX IF NOT EXISTS visits_last ON visits(last_at);
SQL);
            $pdo->prepare('UPDATE pages SET body = REPLACE(body, ?, ?) WHERE slug = ?')->execute([
                '<li><strong>Your IP address</strong>, in hashed form, to limit how many new addresses one network can make per day.</li>',
                '<li><strong>Your IP address</strong>, in hashed form, to limit how many new addresses one network can make per day.</li>' . "\n" . '<li><strong>Visit statistics, for 30 days.</strong> For each visit we keep your IP address, browser, device type, country, the site you came from, how long you stayed and how many addresses you made. We use it to see how many people use the site and to stop abuse. It is deleted automatically after 30 days and never sold or shared.</li>',
                'privacy',
            ]);
            $pdo->exec('PRAGMA user_version = 12');
        }
        if ($version < 13) {
            // First page a visitor opened, and alt text for the blog cover image.
            $pdo->exec("ALTER TABLE visits ADD COLUMN landing TEXT NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE posts ADD COLUMN cover_alt TEXT NOT NULL DEFAULT ''");
            $pdo->exec('PRAGMA user_version = 13');
        }
        if ($version < 14) {
            // Bot flag for Visitors: reason text, empty = real person. Old rows are classified from what we stored.
            $pdo->exec("ALTER TABLE visits ADD COLUMN bot TEXT NOT NULL DEFAULT ''");
            $upd = $pdo->prepare('UPDATE visits SET bot = ? WHERE id = ?');
            foreach ($pdo->query('SELECT id, ip, browser, bver FROM visits')->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $why = Visitors::botReason((string) $v['ip'], (string) $v['browser'], (string) $v['bver']);
                if ($why !== '') {
                    $upd->execute([$why, $v['id']]);
                }
            }
            $pdo->exec('PRAGMA user_version = 14');
        }
    }

    /** Domains managed from /admin, daily counters that outlive cleanup, and small key/value settings. */
    private static function addAdminTables(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS domains (
    name TEXT PRIMARY KEY,
    active INTEGER NOT NULL DEFAULT 1,
    position INTEGER NOT NULL DEFAULT 0,
    added_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS stats (
    day TEXT NOT NULL,
    metric TEXT NOT NULL,
    n INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (day, metric)
);
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
SQL);
        $insert = $pdo->prepare('INSERT OR IGNORE INTO domains (name, active, position, added_at) VALUES (?, 1, ?, ?)');
        foreach (array_values((array) Config::get('domains')) as $i => $name) {
            $insert->execute([strtolower((string) $name), $i, time()]);
        }
        // Count what is already stored so the charts do not start at zero.
        $bump = $pdo->prepare('INSERT INTO stats (day, metric, n) VALUES (?, ?, 1) ON CONFLICT(day, metric) DO UPDATE SET n = n + 1');
        foreach ($pdo->query('SELECT created_at AS t FROM mailboxes')->fetchAll() as $row) {
            $bump->execute([Stats::day((int) $row['t']), 'mailboxes']);
        }
        foreach ($pdo->query('SELECT received_at AS t FROM messages')->fetchAll() as $row) {
            $bump->execute([Stats::day((int) $row['t']), 'emails']);
        }
        $pdo->exec('PRAGMA user_version = 3');
    }

    /**
     * A permanent record of every address made and every email received, for /admin.
     * Visitors never see it; deleting a mailbox or the 3-hour cleanup does not touch it.
     */
    private static function addHistoryTables(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS mail_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id INTEGER,
    address TEXT NOT NULL,
    from_name TEXT,
    from_addr TEXT,
    subject TEXT,
    text_body TEXT,
    otp TEXT,
    size INTEGER NOT NULL DEFAULT 0,
    attachments INTEGER NOT NULL DEFAULT 0,
    received_at INTEGER NOT NULL,
    sent_at INTEGER,
    via TEXT,
    trace TEXT
);
CREATE INDEX IF NOT EXISTS mail_log_time ON mail_log(received_at);
CREATE INDEX IF NOT EXISTS mail_log_address ON mail_log(address);
CREATE TABLE IF NOT EXISTS mailbox_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    address TEXT NOT NULL,
    account_id INTEGER,
    created_at INTEGER NOT NULL,
    ended_at INTEGER,
    end_reason TEXT
);
CREATE INDEX IF NOT EXISTS mailbox_log_time ON mailbox_log(created_at);
CREATE INDEX IF NOT EXISTS mailbox_log_address ON mailbox_log(address, ended_at);
INSERT INTO mailbox_log (address, account_id, created_at)
    SELECT address, account_id, created_at FROM mailboxes ORDER BY created_at;
INSERT INTO mail_log (message_id, address, from_name, from_addr, subject, text_body, otp, size, attachments, received_at, sent_at, via, trace)
    SELECT msg.id, box.address, msg.from_name, msg.from_addr, msg.subject, msg.text_body, msg.otp, msg.size,
        (SELECT COUNT(*) FROM attachments WHERE message_id = msg.id), msg.received_at, msg.sent_at, msg.via, msg.trace
    FROM messages msg JOIN mailboxes box ON box.id = msg.mailbox_id ORDER BY msg.received_at;
PRAGMA user_version = 6;
SQL);
    }

    public static function setting(string $key): ?string
    {
        $row = self::one('SELECT value FROM settings WHERE key = ?', [$key]);
        return $row ? (string) $row['value'] : null;
    }

    public static function setSetting(string $key, string $value): void
    {
        self::run('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)', [$key, $value]);
    }

    private static function createTables(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY,
    created_at INTEGER NOT NULL,
    last_seen_at INTEGER NOT NULL,
    recovery_hash TEXT UNIQUE,
    linked INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS devices (
    id INTEGER PRIMARY KEY,
    account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    user_agent TEXT,
    created_at INTEGER NOT NULL,
    last_seen_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS mailboxes (
    id INTEGER PRIMARY KEY,
    account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    address TEXT NOT NULL UNIQUE,
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS mailboxes_account ON mailboxes(account_id);
CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY,
    mailbox_id INTEGER NOT NULL REFERENCES mailboxes(id) ON DELETE CASCADE,
    from_name TEXT,
    from_addr TEXT,
    subject TEXT,
    text_body TEXT,
    html_body TEXT,
    otp TEXT,
    link TEXT,
    size INTEGER NOT NULL DEFAULT 0,
    seen INTEGER NOT NULL DEFAULT 0,
    received_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS messages_mailbox ON messages(mailbox_id, received_at);
CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY,
    message_id INTEGER NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
    filename TEXT NOT NULL,
    mime TEXT NOT NULL,
    size INTEGER NOT NULL,
    content_id TEXT,
    path TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS attachments_message ON attachments(message_id);
CREATE TABLE IF NOT EXISTS released_addresses (
    address TEXT PRIMARY KEY,
    released_at INTEGER NOT NULL,
    reason TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS rate_limits (
    bucket TEXT PRIMARY KEY,
    hits INTEGER NOT NULL,
    expires_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS link_codes (
    code TEXT PRIMARY KEY,
    account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    expires_at INTEGER NOT NULL
);
PRAGMA user_version = 1;
SQL);
    }
}
