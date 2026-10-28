<?php
declare(strict_types=1);

namespace Tms;

/** Picks out verification codes and confirm links so the inbox can offer one-tap copy/open. */
final class Otp
{
    public static function find(string $text): ?string
    {
        $text = preg_replace('/\s+/u', ' ', $text);
        $keywords = '(?i:code|otp|pin|passcode|password|verification|verify|security|confirm|token|কোড)';
        $patterns = [
            // "Your code is 482913", "verification code: 123-456"
            '/' . $keywords . '.{0,30}?(?<![\\w-])(\\d{3}[- ]?\\d{3}|\\d{4,8})(?![\\w-])/u',
            // Letters and digits, e.g. Steam Guard "F7K2M" or "G-482913"
            '/' . $keywords . '.{0,30}?(?<![\\w-])((?=[A-Z0-9-]*\\d)(?=[A-Z0-9-]*[A-Z])[A-Z0-9]{1,4}-?[A-Z0-9]{3,8})(?![\\w-])/u',
            // "482913 is your code"
            '/(?<![\\w-])((?:[A-Z]{1,3}-)?(?:\\d{3}[- ]\\d{3}|\\d{4,8}))(?![\\w-]).{0,30}' . $keywords . '/u',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m) && !self::looksLikeYear($m[1])) {
                return $m[1];
            }
        }
        if (preg_match('/(?<![\d.,:\/-])(\d{6})(?![\d.,:\/-])/', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function findLink(string $text, string $html): ?string
    {
        $urls = [];
        if (preg_match_all('#href=["\'](https?://[^"\']+)#i', $html, $m)) {
            $urls = array_map(fn ($u) => html_entity_decode($u, ENT_QUOTES | ENT_HTML5), $m[1]);
        }
        if (preg_match_all('#https?://[^\s<>"\')\]]+#i', $text, $m)) {
            $urls = array_merge($urls, $m[0]);
        }
        foreach ($urls as $url) {
            if (preg_match('/verif|confirm|activat|validate|magic|login|signin|sign-in|reset/i', $url)
                && !preg_match('/unsubscribe|privacy|terms/i', $url)) {
                return mb_substr($url, 0, 2000);
            }
        }
        return null;
    }

    private static function looksLikeYear(string $v): bool
    {
        return (bool) preg_match('/^(19|20)\d{2}$/', $v);
    }
}
