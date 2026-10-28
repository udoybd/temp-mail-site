<?php
declare(strict_types=1);

namespace Tms;

/**
 * No sign-up: each browser gets a random device cookie tied to an account.
 * A recovery code or a QR link turns it into a "linked" account with longer-lived mailboxes.
 */
final class Account
{
    public const COOKIE = 'tms_dev';

    /** Current account id, or null when this browser has none yet. */
    public static function current(): ?int
    {
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || strlen($token) !== 64) {
            return null;
        }
        $row = Db::one('SELECT id, account_id, last_seen_at FROM devices WHERE token_hash = ?', [hash('sha256', $token)]);
        if (!$row) {
            return null;
        }
        $now = time();
        if ($now - (int) $row['last_seen_at'] > 3600) {
            Db::run('UPDATE devices SET last_seen_at = ? WHERE id = ?', [$now, $row['id']]);
            Db::run('UPDATE accounts SET last_seen_at = ? WHERE id = ?', [$now, $row['account_id']]);
        }
        return (int) $row['account_id'];
    }

    public static function currentOrCreate(): int
    {
        $id = self::current();
        if ($id !== null) {
            return $id;
        }
        $now = time();
        $id = Db::insert('INSERT INTO accounts (created_at, last_seen_at) VALUES (?, ?)', [$now, $now]);
        self::attachDevice($id);
        return $id;
    }

    public static function attachDevice(int $accountId): void
    {
        $token = bin2hex(random_bytes(32));
        $now = time();
        Db::insert(
            'INSERT INTO devices (account_id, token_hash, user_agent, created_at, last_seen_at) VALUES (?, ?, ?, ?, ?)',
            [$accountId, hash('sha256', $token), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200), $now, $now]
        );
        setcookie(self::COOKIE, $token, [
            'expires' => $now + 400 * 86400,
            'path' => '/',
            'secure' => Http::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $token;
    }

    public static function forgetDevice(): void
    {
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (is_string($token) && $token !== '') {
            Db::run('DELETE FROM devices WHERE token_hash = ?', [hash('sha256', $token)]);
        }
        setcookie(self::COOKIE, '', ['expires' => 1, 'path' => '/']);
        unset($_COOKIE[self::COOKIE]);
    }

    public static function info(int $id): array
    {
        $row = Db::one('SELECT linked, recovery_hash FROM accounts WHERE id = ?', [$id]);
        $devices = (int) (Db::one('SELECT COUNT(*) AS n FROM devices WHERE account_id = ?', [$id])['n'] ?? 0);
        return [
            'linked' => (bool) ($row['linked'] ?? false),
            'has_recovery_code' => !empty($row['recovery_hash']),
            'devices' => $devices,
        ];
    }

    /** Makes a new recovery code (the old one stops working) and returns it once. */
    public static function newRecoveryCode(int $id): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 16; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $code = implode('-', str_split($code, 4));
        Db::run('UPDATE accounts SET recovery_hash = ? WHERE id = ?', [self::hashCode($code), $id]);
        self::markLinked($id);
        return $code;
    }

    public static function findByRecoveryCode(string $code): ?int
    {
        $row = Db::one('SELECT id FROM accounts WHERE recovery_hash = ?', [self::hashCode($code)]);
        return $row ? (int) $row['id'] : null;
    }

    /** Signs this browser into the account (used by recovery code and QR link). */
    public static function switchTo(int $accountId): void
    {
        self::forgetDevice();
        self::attachDevice($accountId);
        self::markLinked($accountId);
    }

    public static function markLinked(int $id): void
    {
        $row = Db::one('SELECT linked FROM accounts WHERE id = ?', [$id]);
        if ($row && !(int) $row['linked']) {
            Db::run('UPDATE accounts SET linked = 1 WHERE id = ?', [$id]);
            // Guest mailboxes get the longer linked lifetime from now on.
            $until = time() + (int) Config::get('linked_mailbox_ttl');
            Db::run('UPDATE mailboxes SET expires_at = MAX(expires_at, ?) WHERE account_id = ?', [$until, $id]);
        }
    }

    public static function newLinkCode(int $id): array
    {
        Db::run('DELETE FROM link_codes WHERE expires_at < ?', [time()]);
        $code = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $expires = time() + 600;
        Db::run('INSERT INTO link_codes (code, account_id, expires_at) VALUES (?, ?, ?)', [$code, $id, $expires]);
        return ['code' => $code, 'expires_at' => $expires];
    }

    public static function useLinkCode(string $code): ?int
    {
        $row = Db::one('SELECT account_id FROM link_codes WHERE code = ? AND expires_at >= ?', [$code, time()]);
        if (!$row) {
            return null;
        }
        Db::run('DELETE FROM link_codes WHERE code = ?', [$code]);
        return (int) $row['account_id'];
    }

    /** Deletes the account with all its mail. Addresses are remembered so they are never handed out again. */
    public static function delete(int $id): void
    {
        foreach (Db::all('SELECT id FROM mailboxes WHERE account_id = ?', [$id]) as $box) {
            Mailbox::delete((int) $box['id'], 'account deleted');
        }
        Db::run('DELETE FROM accounts WHERE id = ?', [$id]);
        setcookie(self::COOKIE, '', ['expires' => 1, 'path' => '/']);
        unset($_COOKIE[self::COOKIE]);
    }

    private static function hashCode(string $code): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        return hash_hmac('sha256', $clean, (string) Config::get('app_key'));
    }
}
