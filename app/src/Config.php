<?php
declare(strict_types=1);

namespace Tms;

/**
 * Settings live in storage/config.php, which is created on first run so the
 * site works with no manual setup. Edit that file to change domains, limits or ads.
 */
final class Config
{
    private static array $values = [];

    public static function defaults(): array
    {
        return [
            'site_name' => 'Temp Mail Site',
            'site_url' => 'https://jamaluddin.info',
            // Domains that receive mail. The first one is the default for new addresses.
            'domains' => ['jamaluddin.info'],
            // Local parts nobody can take (real mailboxes and role addresses).
            'reserved' => ['admin', 'administrator', 'support', 'postmaster', 'abuse', 'info', 'root',
                'webmaster', 'hostmaster', 'noreply', 'no-reply', 'mail', 'security', 'contact', 'help'],
            'support_email' => 'support@jamaluddin.info',
            'allow_custom_names' => true,
            'email_ttl' => 3 * 3600,            // each email is deleted 3 hours after it arrives
            'guest_mailbox_ttl' => 5 * 86400,   // guest mailbox lifetime
            'linked_mailbox_ttl' => 30 * 86400, // lifetime once the account has a recovery code or linked device
            'idle_account_ttl' => 30 * 86400,
            'mailboxes_per_ip_per_day' => 30,
            'max_mailboxes_per_account' => 10,
            'max_message_bytes' => 10 * 1024 * 1024,
            'poll_interval' => 2,           // seconds between inbox checks in the browser
            'ingest_secret' => '',              // shared secret for the Cloudflare Email Worker webhook
            'adsense_client' => '',             // e.g. ca-pub-1234567890; ads stay hidden while empty
            'adsense_slot' => '',
            'analytics_id' => '',               // Google Analytics G-XXXX, optional
        ];
    }

    public static function load(): void
    {
        $file = TMS_STORAGE . '/config.php';
        if (!is_file($file)) {
            self::install($file);
        }
        $saved = require $file;
        self::$values = array_replace(self::defaults(), is_array($saved) ? $saved : []);
    }

    public static function get(string $key)
    {
        return self::$values[$key] ?? null;
    }

    public static function set(string $key, $value): void
    {
        self::$values[$key] = $value;
    }

    private static function install(string $file): void
    {
        foreach (['', '/attachments', '/logs'] as $dir) {
            if (!is_dir(TMS_STORAGE . $dir)) {
                mkdir(TMS_STORAGE . $dir, 0750, true);
            }
        }
        @file_put_contents(TMS_STORAGE . '/.htaccess', "Require all denied\n");
        // Only the per-site values are written; everything else keeps following defaults() on updates.
        $values = [
            'site_url' => self::defaults()['site_url'],
            'domains' => self::defaults()['domains'],
            'adsense_client' => '',
            'ingest_secret' => bin2hex(random_bytes(24)),
            'app_key' => bin2hex(random_bytes(32)),
        ];
        $php = "<?php\n// Temp Mail Site settings. Generated on first run; safe to edit.\nreturn " . var_export($values, true) . ";\n";
        file_put_contents($file, $php, LOCK_EX);
        @chmod($file, 0640);
    }
}
