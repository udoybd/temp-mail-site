<?php
declare(strict_types=1);

namespace Tms;

use PDO;

/**
 * Everything the owner edits from /admin: blog posts, page text, FAQ, site settings
 * and contact-form messages. Defaults ship in app/content/ and are copied in once.
 */
final class Content
{
    /** Pages the owner can edit: slug => [default title, default meta description]. Order = admin list order. */
    public const PAGES = [
        'features' => ['Features', 'Everything Temp Mail Site can do: instant address, live inbox, one-tap codes, custom names, attachments and device sync.'],
        'faq' => ['Frequently asked questions', 'Answers about temporary email: how long it lasts, verification codes, safety, custom addresses and more.'],
        'contact' => ['Contact us', 'Get in touch with the Temp Mail Site team for help, feedback or abuse reports.'],
        'how-it-works' => ['How Temp Mail Site works', 'Get a free disposable email in three steps: copy the address, use it anywhere, read the code and let it expire.'],
        'about' => ['About us', 'Temp Mail Site gives you a free, private, disposable email address with no sign-up.'],
        'support' => ['Support', 'Get help with your temporary inbox, recovery code or account.'],
        'privacy' => ['Privacy Policy', 'What Temp Mail Site stores, for how long, and who can see it.'],
        'terms' => ['Terms of Use', 'The rules for using Temp Mail Site.'],
    ];

    /** Site-wide text and links editable under Admin → Settings. key => [label, default]. */
    public const SETTINGS = [
        'hero_badge' => ['Small line above the title', 'Instant inbox · Works on phone and desktop'],
        'hero_title' => ['Home page title', 'Free Temporary Email Address'],
        'hero_ticks' => ['Ticks under the title (comma separated)', 'No sign-up, Auto-expires, Free forever'],
        'footer_text' => ['Footer text under the logo', 'Free temporary email service to keep your inbox clean and safe.'],
        'social_facebook' => ['Facebook page link', ''],
        'social_x' => ['X (Twitter) link', ''],
        'social_telegram' => ['Telegram link', ''],
        'social_youtube' => ['YouTube link', ''],
    ];

    // ---- schema -------------------------------------------------------------

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    cover TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft',
    published_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS posts_published ON posts(status, published_at);
CREATE TABLE IF NOT EXISTS pages (
    slug TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS faqs (
    id INTEGER PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    position INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS contact_messages (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    subject TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    ip TEXT NOT NULL DEFAULT '',
    seen INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL
);
SQL);
        // The guides that used to live in app/content/blog/ become editable posts.
        $insert = $pdo->prepare('INSERT OR IGNORE INTO posts (slug, title, description, body, status, published_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach (glob(TMS_ROOT . '/app/content/blog/*.php') ?: [] as $file) {
            $p = require $file;
            $at = strtotime($p['date'] . ' 09:00:00 UTC') ?: time();
            $insert->execute([basename($file, '.php'), $p['title'], $p['description'], trim($p['body']), 'published', $at, $at]);
        }
        $insert = $pdo->prepare('INSERT INTO faqs (question, answer, position) VALUES (?, ?, ?)');
        foreach (require TMS_ROOT . '/app/content/faq.php' as $i => [$q, $a]) {
            $insert->execute([$q, $a, $i]);
        }
    }

    // ---- posts --------------------------------------------------------------

    /** Published posts, newest first. */
    public static function posts(int $limit = 100): array
    {
        return Db::all("SELECT * FROM posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC LIMIT " . max(1, $limit), [time()]);
    }

    public static function post(string $slug): ?array
    {
        return Db::one("SELECT * FROM posts WHERE slug = ? AND status = 'published' AND published_at <= ?", [$slug, time()]);
    }

    public static function slugify(string $text): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
        return substr($slug, 0, 80) ?: 'post';
    }

    // ---- pages --------------------------------------------------------------

    /** @return array{title: string, description: string, body: string, custom: bool} */
    public static function page(string $slug): array
    {
        [$title, $description] = self::PAGES[$slug];
        $row = Db::one('SELECT * FROM pages WHERE slug = ?', [$slug]);
        if ($row) {
            return ['title' => $row['title'], 'description' => $row['description'], 'body' => $row['body'], 'custom' => true];
        }
        return ['title' => $title, 'description' => $description, 'body' => self::defaultBody($slug), 'custom' => false];
    }

    /** The page text that ships with the site, used until the owner saves their own. */
    public static function defaultBody(string $slug): string
    {
        $file = TMS_ROOT . '/app/content/pages/' . $slug . '.php';
        if (!isset(self::PAGES[$slug]) || !is_file($file)) {
            return '';
        }
        $e = [Http::class, 'e'];
        $site = (string) Config::get('site_name');
        $support = (string) Config::get('support_email');
        $domain = Mailbox::domains()[0] ?? 'example.com';
        ob_start();
        require $file;
        return trim((string) ob_get_clean());
    }

    // ---- faq ----------------------------------------------------------------

    public static function faqs(): array
    {
        return Db::all('SELECT * FROM faqs ORDER BY position, id');
    }

    // ---- settings -----------------------------------------------------------

    public static function setting(string $key): string
    {
        $value = Db::setting('content_' . $key);
        return $value ?? (self::SETTINGS[$key][1] ?? '');
    }

    /** @return array<string, string> */
    public static function settings(): array
    {
        $out = [];
        foreach (array_keys(self::SETTINGS) as $key) {
            $out[$key] = self::setting($key);
        }
        return $out;
    }

    // ---- uploads ------------------------------------------------------------

    /**
     * Saves an uploaded image under public/uploads/ and returns its URL.
     * Only real JPEG/PNG/GIF/WebP images are kept, with a random name, so nothing there can run.
     */
    public static function saveImage(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new UserError('The image did not upload. Try a smaller file (under 5 MB).');
        }
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            throw new UserError('That image is bigger than 5 MB.');
        }
        $info = @getimagesize((string) $file['tmp_name']);
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if ($ext === null) {
            throw new UserError('Only JPG, PNG, GIF or WebP images can be uploaded.');
        }
        $dir = TMS_ROOT . '/public/uploads/' . gmdate('Y-m');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new UserError('Could not create the uploads folder on the server.');
        }
        $guard = TMS_ROOT . '/public/uploads/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\nRequire all denied\n</FilesMatch>\n");
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            throw new UserError('Could not save the image.');
        }
        @chmod($dir . '/' . $name, 0644);
        return '/uploads/' . gmdate('Y-m') . '/' . $name;
    }
}
