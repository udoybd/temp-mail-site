<?php
declare(strict_types=1);

namespace Tms;

final class Mailbox
{
    private const WORDS_A = ['amber', 'brave', 'calm', 'clever', 'cosmic', 'crisp', 'daring', 'eager', 'fancy', 'fuzzy',
        'gentle', 'happy', 'jolly', 'lucky', 'mellow', 'misty', 'noble', 'proud', 'quick', 'quiet', 'rapid', 'shiny',
        'silent', 'sly', 'snowy', 'solar', 'sunny', 'swift', 'tidy', 'wild', 'witty', 'zesty'];
    private const WORDS_B = ['badger', 'bear', 'comet', 'eagle', 'falcon', 'fox', 'gecko', 'heron', 'koala', 'lemur',
        'lion', 'lynx', 'maple', 'meteor', 'otter', 'owl', 'panda', 'pebble', 'pine', 'puffin', 'raven', 'river',
        'robin', 'shark', 'tiger', 'tulip', 'walrus', 'whale', 'willow', 'wolf', 'yak', 'zebra'];

    /** Active mail domains, default first. Managed from /admin; config.php seeds the list. */
    public static function domains(): array
    {
        $rows = Db::all('SELECT name FROM domains WHERE active = 1 ORDER BY position, added_at');
        if (!$rows) {
            return array_values(array_map('strtolower', (array) Config::get('domains')));
        }
        return array_column($rows, 'name');
    }

    public static function forAccount(int $accountId): array
    {
        return Db::all(
            'SELECT m.id, m.address, m.created_at, m.expires_at,
                (SELECT COUNT(*) FROM messages WHERE mailbox_id = m.id AND seen = 0) AS unread
             FROM mailboxes m WHERE m.account_id = ? AND m.expires_at > ? ORDER BY m.created_at DESC',
            [$accountId, time()]
        );
    }

    public static function owned(int $accountId, int $mailboxId): ?array
    {
        return Db::one('SELECT * FROM mailboxes WHERE id = ? AND account_id = ?', [$mailboxId, $accountId]);
    }


    /** Every address works for exactly this long after it is made (fixed, not read from config.php). */
    public const LIFETIME = 86400;

    /** Cuts any mailbox that would live longer than LIFETIME, e.g. ones made under the old 5/30-day rule. */
    public static function enforceLifetime(): void
    {
        Db::run('UPDATE mailboxes SET expires_at = created_at + ' . self::LIFETIME . ' WHERE expires_at > created_at + ' . self::LIFETIME);
    }

    /** The account's own live mailbox with this address, if it has one. */
    public static function ownedByAddress(int $accountId, string $address): ?array
    {
        return Db::one(
            'SELECT m.id, m.address, m.created_at, m.expires_at,
                (SELECT COUNT(*) FROM messages WHERE mailbox_id = m.id AND seen = 0) AS unread
             FROM mailboxes m WHERE m.account_id = ? AND m.address = ? AND m.expires_at > ?',
            [$accountId, strtolower($address), time()]
        );
    }

    /** Normalises a typed name and domain into an address, or null when the name is not valid. */
    public static function customAddress(string $local, ?string $domain): ?string
    {
        $domains = self::domains();
        $domain = strtolower(trim((string) $domain));
        if ($domain === '' || !in_array($domain, $domains, true)) {
            $domain = $domains[0];
        }
        $local = strtolower(trim($local));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9._-]{1,28}[a-z0-9])?$/', $local) || str_contains($local, '..')) {
            return null;
        }
        return $local . '@' . $domain;
    }

    /**
     * @throws UserError when the name is taken, invalid or a limit is hit
     */
    public static function create(int $accountId, ?string $local = null, ?string $domain = null): array
    {
        $domains = self::domains();
        $domain = strtolower(trim((string) $domain));
        if ($domain === '' || !in_array($domain, $domains, true)) {
            $domain = $domains[0];
        }

        $count = (int) Db::one('SELECT COUNT(*) AS n FROM mailboxes WHERE account_id = ? AND expires_at > ?', [$accountId, time()])['n'];
        if ($count >= (int) Config::get('max_mailboxes_per_account')) {
            throw new UserError('You have reached the mailbox limit. Delete an old address first.');
        }

        if ($local !== null && trim($local) !== '') {
            if (!Config::get('allow_custom_names')) {
                throw new UserError('Custom addresses are not available.');
            }
            $local = strtolower(trim($local));
            if (!preg_match('/^[a-z0-9](?:[a-z0-9._-]{1,28}[a-z0-9])?$/', $local) || str_contains($local, '..')) {
                throw new UserError('Use 3 to 30 letters, numbers, dots, dashes or underscores.');
            }
            // A live address belongs to whoever made it until it expires (24 hours). Deleted or expired ones are free at once.
            $live = Db::one('SELECT expires_at FROM mailboxes WHERE address = ?', [$local . '@' . $domain]);
            if ($live) {
                $h = max(1, (int) ceil(((int) $live['expires_at'] - time()) / 3600));
                throw new UserError('That address is in use. It becomes free in about ' . $h . ($h === 1 ? ' hour' : ' hours') . ', or pick another name.');
            }
            if (!self::available($local . '@' . $domain)) {
                throw new UserError('That address is taken. Try another name.');
            }
        } else {
            $local = self::randomFree($domain);
        }

        $ttl = self::LIFETIME;
        $now = time();
        $address = $local . '@' . $domain;
        $id = Db::insert(
            'INSERT INTO mailboxes (account_id, address, created_at, expires_at) VALUES (?, ?, ?, ?)',
            [$accountId, $address, $now, $now + $ttl]
        );
        Db::run('INSERT INTO mailbox_log (address, account_id, created_at) VALUES (?, ?, ?)', [$address, $accountId, $now]);
        Stats::bump('mailboxes');
        Visitors::bumpMail();
        return ['id' => $id, 'address' => $address, 'created_at' => $now, 'expires_at' => $now + $ttl, 'unread' => 0];
    }

    public static function available(string $address): bool
    {
        [$local] = explode('@', $address, 2);
        if (in_array($local, (array) Config::get('reserved'), true)) {
            return false;
        }
        return !Db::one('SELECT 1 FROM mailboxes WHERE address = ?', [$address]);
    }

    public static function delete(int $mailboxId, string $reason): void
    {
        $box = Db::one('SELECT address FROM mailboxes WHERE id = ?', [$mailboxId]);
        if (!$box) {
            return;
        }
        foreach (Db::all('SELECT id FROM messages WHERE mailbox_id = ?', [$mailboxId]) as $msg) {
            Message::delete((int) $msg['id']);
        }
        Db::run('DELETE FROM mailboxes WHERE id = ?', [$mailboxId]);
        Db::run(
            'INSERT OR REPLACE INTO released_addresses (address, released_at, reason) VALUES (?, ?, ?)',
            [$box['address'], time(), $reason]
        );
        Db::run('UPDATE mailbox_log SET ended_at = ?, end_reason = ? WHERE address = ? AND ended_at IS NULL', [time(), $reason, $box['address']]);
    }

    /** Finds the live mailbox for any of the given recipient addresses. */
    public static function findForRecipients(array $recipients): ?array
    {
        foreach ($recipients as $rcpt) {
            $rcpt = strtolower(trim($rcpt));
            // Drop "+tag" so user+anything@domain still lands in user@domain.
            $rcpt = preg_replace('/\+[^@]*@/', '@', $rcpt);
            $box = Db::one('SELECT * FROM mailboxes WHERE address = ? AND expires_at > ?', [$rcpt, time()]);
            if ($box) {
                return $box;
            }
        }
        return null;
    }

    private static function randomFree(string $domain): string
    {
        for ($i = 0; $i < 20; $i++) {
            $local = self::WORDS_A[random_int(0, count(self::WORDS_A) - 1)]
                . self::WORDS_B[random_int(0, count(self::WORDS_B) - 1)]
                . random_int(10, 9999);
            if (self::available($local . '@' . $domain)) {
                return $local;
            }
        }
        return 'tm' . bin2hex(random_bytes(5));
    }
}
