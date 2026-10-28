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
