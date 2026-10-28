<?php
declare(strict_types=1);

namespace Tms;

final class Api
{
    public static function handle(string $method, string $path): void
    {
        try {
            if ($method !== 'GET' && $path !== '/api/incoming' && ($_SERVER['HTTP_X_TMS'] ?? '') !== '1') {
                throw new UserError('Bad request.', 400);
            }
            self::route($method, $path);
        } catch (UserError $e) {
            Http::json(['error' => $e->getMessage()], $e->status());
        } catch (\Throwable $e) {
            Log::write('error', $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            Http::json(['error' => 'Something went wrong. Please try again.'], 500);
        }
    }

    private static function route(string $method, string $path): void
    {
        $body = $method === 'GET' ? [] : Http::body();

        if ($method === 'GET' && $path === '/api/state') {
            $account = Account::current();
            Http::json([
                'domains' => Mailbox::domains(),
                'allow_custom' => (bool) Config::get('allow_custom_names'),
                'poll_seconds' => (int) Config::get('poll_seconds'),
                'account' => $account ? Account::info($account) : null,
                'mailboxes' => $account ? self::boxes($account) : [],
            ]);
            return;
        }

        if ($method === 'POST' && $path === '/api/mailboxes') {
            if (!RateLimit::hit('mbox:' . Http::ip(), (int) Config::get('mailboxes_per_ip_per_day'), 86400)) {
                throw new UserError('Daily limit for new addresses reached on this network. Try again tomorrow.', 429);
            }
            $account = Account::currentOrCreate();
            $box = Mailbox::create($account, $body['name'] ?? null, $body['domain'] ?? null);
            Http::json(['mailbox' => self::box($box)], 201);
            return;
        }

        if (preg_match('#^/api/mailboxes/(\d+)$#', $path, $m)) {
            $account = self::requireAccount();
            $box = Mailbox::owned($account, (int) $m[1]) ?? throw new UserError('Mailbox not found.', 404);
            if ($method === 'DELETE') {
                Mailbox::delete((int) $box['id'], 'deleted by owner');
                Http::json(['ok' => true]);
                return;
            }
            if ($method === 'GET') {
                Http::json(['mailbox' => self::box($box), 'messages' => array_map([self::class, 'summary'], Message::listFor((int) $box['id']))]);
                return;
            }
        }

        if (preg_match('#^/api/messages/(\d+)$#', $path, $m)) {
            $account = self::requireAccount();
            $msg = Message::owned($account, (int) $m[1]) ?? throw new UserError('Email not found.', 404);
            if ($method === 'DELETE') {
                Message::delete((int) $msg['id']);
                Http::json(['ok' => true]);
                return;
            }
            if ($method === 'GET') {
                Db::run('UPDATE messages SET seen = 1 WHERE id = ?', [$msg['id']]);
                $out = self::summary($msg);
                $out['text'] = $msg['text_body'];
                $out['has_html'] = trim((string) $msg['html_body']) !== '';
                $out['attachments'] = array_map(fn ($a) => [
                    'id' => (int) $a['id'], 'filename' => $a['filename'], 'mime' => $a['mime'], 'size' => (int) $a['size'],
                    'url' => '/attachment/' . $a['id'],
                ], Message::attachments((int) $msg['id']));
                Http::json(['message' => $out]);
                return;
            }
        }

        if ($method === 'POST' && $path === '/api/account/recovery-code') {
            $account = Account::currentOrCreate();
            Http::json(['code' => Account::newRecoveryCode($account), 'account' => Account::info($account)]);
            return;
        }

        if ($method === 'POST' && $path === '/api/account/restore') {
            if (!RateLimit::hit('restore:' . Http::ip(), 20, 3600)) {
                throw new UserError('Too many attempts. Wait an hour and try again.', 429);
            }
            $id = Account::findByRecoveryCode((string) ($body['code'] ?? '')) ?? throw new UserError('That recovery code is not valid.');
            Account::switchTo($id);
            Http::json(['ok' => true]);
            return;
        }

        if ($method === 'POST' && $path === '/api/account/link-code') {
            $account = Account::currentOrCreate();
            $link = Account::newLinkCode($account);
            Http::json(['url' => rtrim((string) Config::get('site_url'), '/') . '/link/' . $link['code'], 'expires_at' => $link['expires_at']]);
            return;
        }

        if ($method === 'POST' && $path === '/api/account/delete') {
            $code = trim((string) ($body['code'] ?? ''));
            if ($code !== '') {
                if (!RateLimit::hit('restore:' . Http::ip(), 20, 3600)) {
                    throw new UserError('Too many attempts. Wait an hour and try again.', 429);
                }
                $id = Account::findByRecoveryCode($code) ?? throw new UserError('That recovery code is not valid.');
            } else {
                $id = self::requireAccount();
            }
            Account::delete($id);
            Http::json(['ok' => true]);
            return;
        }

        if ($method === 'POST' && $path === '/api/incoming') {
            self::incoming();
            return;
        }

        throw new UserError('Not found.', 404);
    }

    /** Webhook used by the Cloudflare Email Worker for mail domains hosted on Cloudflare. */
    private static function incoming(): void
    {
        $secret = (string) Config::get('ingest_secret');
        if ($secret === '' || !hash_equals($secret, (string) ($_SERVER['HTTP_X_INGEST_SECRET'] ?? ''))) {
            throw new UserError('Forbidden.', 403);
        }
        $raw = (string) file_get_contents('php://input');
        $rcpt = (string) ($_SERVER['HTTP_X_RCPT_TO'] ?? '');
        $id = Message::ingest($raw, $rcpt !== '' ? [$rcpt] : []);
        Http::json(['stored' => $id !== null]);
    }

    private static function requireAccount(): int
    {
        return Account::current() ?? throw new UserError('No mailbox on this device yet.', 401);
    }

    private static function boxes(int $account): array
    {
        return array_map([self::class, 'box'], Mailbox::forAccount($account));
    }

    private static function box(array $b): array
    {
        return [
            'id' => (int) $b['id'],
            'address' => $b['address'],
            'created_at' => (int) $b['created_at'],
            'expires_at' => (int) $b['expires_at'],
            'unread' => (int) ($b['unread'] ?? 0),
        ];
    }

    private static function summary(array $m): array
    {
        $from = (string) $m['from_addr'];
        return [
            'id' => (int) $m['id'],
            'from_name' => $m['from_name'] ?: $from,
            'from_addr' => $from,
            'domain' => str_contains($from, '@') ? substr($from, strrpos($from, '@') + 1) : '',
            'subject' => $m['subject'] !== '' ? $m['subject'] : '(no subject)',
            'preview' => trim(preg_replace('/\s+/u', ' ', (string) ($m['preview'] ?? mb_substr((string) $m['text_body'], 0, 160)))),
            'otp' => $m['otp'],
            'link' => $m['link'],
            'seen' => (bool) $m['seen'],
            'attachments' => (int) ($m['attachments'] ?? 0),
            'received_at' => (int) $m['received_at'],
            'expires_at' => (int) $m['received_at'] + (int) Config::get('email_ttl'),
        ];
    }
}
