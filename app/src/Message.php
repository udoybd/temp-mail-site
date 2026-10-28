<?php
declare(strict_types=1);

namespace Tms;

use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\MailMimeParser;

final class Message
{
    /**
     * Stores a raw RFC 822 message. Returns the new message id, or null when
     * no live mailbox matches (mail to unknown addresses is dropped).
     *
     * @param string[] $envelopeRecipients recipients known from the delivery (pipe env or webhook)
     */
    public static function ingest(string $raw, array $envelopeRecipients = []): ?int
    {
        if (strlen($raw) > (int) Config::get('max_message_bytes')) {
            Log::write('ingest', 'dropped oversized message (' . strlen($raw) . ' bytes)');
            return null;
        }

        // Exim's pipe transport prepends an mbox "From sender date" line; it is not a header.
        if (strncmp($raw, 'From ', 5) === 0) {
            $raw = (string) substr($raw, strpos($raw, "\n") + 1);
        }

        $parser = new MailMimeParser();
        $mail = $parser->parse($raw, false);

        $recipients = $envelopeRecipients;
        foreach (['Envelope-To', 'X-Original-To', 'Delivered-To', HeaderConsts::TO, HeaderConsts::CC] as $name) {
            foreach ($mail->getAllHeadersByName($name) as $header) {
                if (method_exists($header, 'getAddresses')) {
                    foreach ($header->getAddresses() as $addr) {
                        $recipients[] = $addr->getEmail();
                    }
                } else {
                    foreach (preg_split('/[\s,;]+/', (string) $header->getRawValue()) as $part) {
                        if (str_contains($part, '@')) {
                            $recipients[] = trim($part, '<>');
                        }
                    }
                }
            }
        }

        $box = Mailbox::findForRecipients(array_values(array_unique($recipients)));
        if (!$box) {
            return null;
        }

        $from = $mail->getHeader(HeaderConsts::FROM);
        $fromAddr = '';
        $fromName = '';
        if ($from && method_exists($from, 'getAddresses') && $from->getAddresses()) {
            $first = $from->getAddresses()[0];
            $fromAddr = (string) $first->getEmail();
            $fromName = (string) $first->getName();
        }

        $subject = (string) ($mail->getHeaderValue(HeaderConsts::SUBJECT) ?? '');
        $text = (string) ($mail->getTextContent() ?? '');
        $html = (string) ($mail->getHtmlContent() ?? '');
        if ($text === '' && $html !== '') {
            $text = self::htmlToText($html);
        }

        $id = Db::insert(
            'INSERT INTO messages (mailbox_id, from_name, from_addr, subject, text_body, html_body, otp, link, size, received_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $box['id'], mb_substr($fromName, 0, 200), mb_substr($fromAddr, 0, 200), mb_substr($subject, 0, 500),
                $text, $html, Otp::find($subject . "\n" . $text), Otp::findLink($text, $html), strlen($raw), time(),
            ]
        );

        $dir = TMS_STORAGE . '/attachments/' . $id;
        foreach ($mail->getAllAttachmentParts() as $i => $part) {
            $stream = $part->getBinaryContentStream();
            if ($stream === null) {
                continue;
            }
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            $file = $dir . '/' . $i . '.bin';
            file_put_contents($file, $stream->getContents());
            $name = $part->getFilename() ?: ('attachment-' . ($i + 1));
            Db::insert(
                'INSERT INTO attachments (message_id, filename, mime, size, content_id, path) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, mb_substr($name, 0, 200), (string) $part->getContentType(), filesize($file),
                    $part->getContentId() ? trim((string) $part->getContentId(), '<>') : null, $id . '/' . $i . '.bin']
            );
        }
        return $id;
    }

    public static function listFor(int $mailboxId): array
    {
        return Db::all(
            'SELECT id, from_name, from_addr, subject, substr(text_body, 1, 160) AS preview, otp, link, seen, received_at,
                (SELECT COUNT(*) FROM attachments WHERE message_id = messages.id) AS attachments
             FROM messages WHERE mailbox_id = ? ORDER BY received_at DESC LIMIT 100',
            [$mailboxId]
        );
    }

    /** Message plus its mailbox, only when the account owns it. */
    public static function owned(int $accountId, int $messageId): ?array
    {
        return Db::one(
            'SELECT msg.* FROM messages msg JOIN mailboxes box ON box.id = msg.mailbox_id
             WHERE msg.id = ? AND box.account_id = ?',
            [$messageId, $accountId]
        );
    }

    public static function attachments(int $messageId): array
    {
        return Db::all('SELECT id, filename, mime, size, content_id FROM attachments WHERE message_id = ?', [$messageId]);
    }

    public static function delete(int $messageId): void
    {
        foreach (Db::all('SELECT path FROM attachments WHERE message_id = ?', [$messageId]) as $att) {
            @unlink(TMS_STORAGE . '/attachments/' . $att['path']);
        }
        @rmdir(TMS_STORAGE . '/attachments/' . $messageId);
        Db::run('DELETE FROM messages WHERE id = ?', [$messageId]);
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(br|/p|/div|/tr|/h[1-6]|/li)[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));
    }
}
