<?php
declare(strict_types=1);

namespace Tms;

final class View
{
    private const PAGES = [
        '/how-it-works' => ['how-it-works', 'How Temp Mail Site works', 'Get a free disposable email in three steps: copy the address, use it anywhere, read the code and let it expire.'],
        '/about' => ['about', 'About', 'Temp Mail Site gives you a free, private, disposable email address with no sign-up.'],
        '/support' => ['support', 'Support', 'Get help with your temporary inbox, recovery code or account.'],
        '/privacy' => ['privacy', 'Privacy Policy', 'What Temp Mail Site stores, for how long, and who can see it.'],
        '/terms' => ['terms', 'Terms of Use', 'The rules for using Temp Mail Site.'],
        '/delete-account' => ['delete-account', 'Delete your account', 'Delete your Temp Mail Site account and every mailbox in it.'],
    ];

    public static function route(string $path): void
    {
        if ($path === '/') {
            self::render('home', [
                'title' => 'Temp Mail: Free Temporary Email Address Generator',
                'description' => 'Get a free temporary email address instantly. No sign-up, live inbox, one-tap verification codes, auto-expiring mailboxes.',
                'home' => true,
            ]);
            return;
        }
        if (isset(self::PAGES[$path])) {
            [$view, $title, $description] = self::PAGES[$path];
            self::render('pages/' . $view, ['title' => $title, 'description' => $description]);
            return;
        }
        if ($path === '/blog') {
            self::render('blog-index', ['title' => 'Guides & tips', 'description' => 'How to use a temporary email for sign-ups, verification codes and privacy.', 'posts' => self::posts()]);
            return;
        }
        if (preg_match('#^/blog/([a-z0-9-]+)$#', $path, $m) && isset(self::posts()[$m[1]])) {
            $post = self::posts()[$m[1]];
            self::render('blog-post', ['title' => $post['title'], 'description' => $post['description'], 'post' => $post, 'slug' => $m[1]]);
            return;
        }
        http_response_code(404);
        self::render('pages/not-found', ['title' => 'Page not found', 'description' => 'This page does not exist.']);
    }

    /** @return array<string, array> slug => post, newest first */
    public static function posts(): array
    {
        static $posts = null;
        if ($posts === null) {
            $posts = [];
            foreach (glob(TMS_ROOT . '/app/content/blog/*.php') ?: [] as $file) {
                $posts[basename($file, '.php')] = require $file;
            }
            uasort($posts, fn ($a, $b) => strcmp($b['date'], $a['date']));
        }
        return $posts;
    }

    public static function render(string $view, array $vars): void
    {
        $vars['site'] = (string) Config::get('site_name');
        $vars['siteUrl'] = rtrim((string) Config::get('site_url'), '/');
        $vars['adsense'] = (string) Config::get('adsense_client');
        $vars['adSlot'] = (string) Config::get('adsense_slot');
        $vars['analytics'] = (string) Config::get('analytics_id');
        $vars['support'] = (string) Config::get('support_email');
        $vars['path'] = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        extract($vars, EXTR_SKIP);
        $e = [Http::class, 'e'];
        ob_start();
        require TMS_ROOT . '/app/views/' . $view . '.php';
        $content = ob_get_clean();
        header('Content-Type: text/html; charset=utf-8');
        require TMS_ROOT . '/app/views/layout.php';
    }

    /** An ad slot that renders only when AdSense is configured. */
    public static function ad(string $client, string $slot): string
    {
        if ($client === '') {
            return '';
        }
        return '<div class="ad"><ins class="adsbygoogle" style="display:block" data-ad-client="' . Http::e($client) . '"'
            . ($slot !== '' ? ' data-ad-slot="' . Http::e($slot) . '"' : '')
            . ' data-ad-format="auto" data-full-width-responsive="true"></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script></div>';
    }
}
