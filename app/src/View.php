<?php
declare(strict_types=1);

namespace Tms;

final class View
{
    /** Fixed pages that are not editable (they hold forms that need code). */
    private const PAGES = [
        '/delete-account' => ['delete-account', 'Delete your account', 'Delete your Bengal Email account and every mailbox in it.'],
    ];

    public static function route(string $path): void
    {
        if ($path === '/') {
            self::render('home', [
                'title' => 'Bengal Email: Free Temporary Email Address Generator',
                'description' => 'Get a free temporary email address instantly. No sign-up, live inbox, one-tap verification codes, auto-expiring mailboxes.',
                'home' => true,
            ]);
            return;
        }
        if ($path === '/inbox' || $path === '/premium') {
            header('Location: /#inbox');
            http_response_code(302);
            return;
        }
        $slug = substr($path, 1);
        if (isset(Content::PAGES[$slug])) {
            $page = Content::page($slug);
            self::render('page', [
                'title' => $page['title'], 'description' => $page['description'], 'page' => $page, 'slug' => $slug,
                'faqs' => $slug === 'faq' ? Content::faqs() : [],
            ]);
            return;
        }
        if (isset(self::PAGES[$path])) {
            [$view, $title, $description] = self::PAGES[$path];
            self::render('pages/' . $view, ['title' => $title, 'description' => $description]);
            return;
        }
        if ($path === '/blog') {
            self::render('blog-index', ['title' => 'Blog', 'description' => 'Guides and tips: how to use a temporary email for sign-ups, verification codes and privacy.', 'posts' => Content::posts()]);
            return;
        }
        if (preg_match('#^/blog/([a-z0-9-]+)$#', $path, $m) && ($post = Content::post($m[1]))) {
            self::render('blog-post', [
                'title' => $post['title'], 'description' => $post['description'], 'post' => $post, 'slug' => $m[1],
                'more' => array_values(array_filter(Content::posts(4), fn ($p) => $p['slug'] !== $m[1])),
            ]);
            return;
        }
        http_response_code(404);
        self::render('pages/not-found', ['title' => 'Page not found', 'description' => 'This page does not exist.']);
    }

    /** Published posts keyed by slug, newest first (used by the sitemap). */
    public static function posts(): array
    {
        $out = [];
        foreach (Content::posts(1000) as $p) {
            $out[$p['slug']] = $p;
        }
        return $out;
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
        $vars['cs'] = Content::settings();
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
