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

    public static function domains(): array
    {
        return array_values(array_map('strtolower', (array) Config::get('domains')));
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
            if (!self::available($local . '@' . $domain)) {
                throw new UserError('That address is taken. Try another name.');
            }
        } else {
            $local = self::randomFree($domain);
        }

        $info = Db::one('SELECT linked FROM accounts WHERE id = ?', [$accountId]);
        $ttl = (int) Config::get(!empty($info['linked']) ? 'linked_mailbox_ttl' : 'guest_mailbox_ttl');
        $now = time();
        $address = $local . '@' . $domain;
        $id = Db::insert(
            'INSERT INTO mailboxes (account_id, address, created_at, expires_at) VALUES (?, ?, ?, ?)',
            [$accountId, $address, $now, $now + $ttl]
        );
        return ['id' => $id, 'address' => $address, 'created_at' => $now, 'expires_at' => $now + $ttl, 'unread' => 0];
    }

    public static function available(string $address): bool
    {
        [$local] = explode('@', $address, 2);
        if (in_array($local, (array) Config::get('reserved'), true)) {
            return false;
        }
        return !Db::one('SELECT 1 FROM mailboxes WHERE address = ?', [$address])
            && !Db::one('SELECT 1 FROM released_addresses WHERE address = ?', [$address]);
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
            'INSERT OR IGNORE INTO released_addresses (address, released_at, reason) VALUES (?, ?, ?)',
            [$box['address'], time(), $reason]
        );
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
