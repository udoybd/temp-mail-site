<?php
declare(strict_types=1);

namespace Tms;

final class Cleanup
{
    /** Deletes expired mail, mailboxes and idle accounts. Returns counts for the log. */
    public static function run(): array
    {
        $now = time();
        $done = ['emails' => 0, 'mailboxes' => 0, 'accounts' => 0];

        foreach (Db::all('SELECT id FROM messages WHERE received_at < ?', [$now - (int) Config::get('email_ttl')]) as $row) {
            Message::delete((int) $row['id']);
            $done['emails']++;
        }
        foreach (Db::all('SELECT id FROM mailboxes WHERE expires_at < ?', [$now]) as $row) {
            Mailbox::delete((int) $row['id'], 'expired');
            $done['mailboxes']++;
        }
        $idle = Db::all(
            'SELECT a.id FROM accounts a WHERE a.last_seen_at < ? AND NOT EXISTS (SELECT 1 FROM mailboxes m WHERE m.account_id = a.id)',
            [$now - (int) Config::get('idle_account_ttl')]
        );
        foreach ($idle as $row) {
            Db::run('DELETE FROM accounts WHERE id = ?', [$row['id']]);
            $done['accounts']++;
        }
        Db::run('DELETE FROM rate_limits WHERE expires_at < ?', [$now]);
        Db::run('DELETE FROM link_codes WHERE expires_at < ?', [$now]);
        return $done;
    }
}
