<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use Tms\Account;
use Tms\Api;
use Tms\Config;
use Tms\Db;
use Tms\Http;
use Tms\Message;
use Tms\View;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (str_starts_with($path, '/api/')) {
    Api::handle($method, $path);
    return;
}

if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    Tms\Admin::handle($method, $path);
    return;
}

// Email HTML is served on its own URL and shown in a sandboxed iframe: no scripts, no access to the site.
if (preg_match('#^/m/(\d+)/html$#', $path, $m)) {
    $account = Account::current();
    $msg = $account ? Message::owned($account, (int) $m[1]) : null;
    if (!$msg) {
        http_response_code(404);
        return;
    }
    $html = (string) $msg['html_body'];
    foreach (Message::attachments((int) $msg['id']) as $att) {
        if ($att['content_id']) {
            $html = str_ireplace('cid:' . $att['content_id'], '/attachment/' . $att['id'] . '?inline=1', $html);
        }
    }
    header("Content-Security-Policy: sandbox allow-popups allow-popups-to-escape-sandbox; default-src 'none'; img-src * data: blob:; style-src 'unsafe-inline' *; font-src * data:");
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{margin:0;padding:12px;font-family:system-ui,sans-serif;word-wrap:break-word}img{max-width:100%;height:auto}</style></head><body>' . $html . '</body></html>';
    return;
}

if (preg_match('#^/attachment/(\d+)$#', $path, $m)) {
    $account = Account::current();
    $att = $account ? Db::one(
        'SELECT a.* FROM attachments a JOIN messages msg ON msg.id = a.message_id JOIN mailboxes box ON box.id = msg.mailbox_id
         WHERE a.id = ? AND box.account_id = ?',
        [(int) $m[1], $account]
    ) : null;
    $file = $att ? TMS_STORAGE . '/attachments/' . $att['path'] : '';
    if (!$att || !is_file($file)) {
        http_response_code(404);
        return;
    }
    $inline = isset($_GET['inline']) && str_starts_with((string) $att['mime'], 'image/');
    header('Content-Type: ' . ($inline ? $att['mime'] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    header("Content-Security-Policy: sandbox; default-src 'none'");
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($att['filename']));
    readfile($file);
    return;
}

if (preg_match('#^/link/([A-Za-z0-9_-]{10,64})$#', $path, $m)) {
    $id = Account::useLinkCode($m[1]);
    if ($id !== null) {
        Account::switchTo($id);
        header('Location: /?linked=1');
    } else {
        header('Location: /?linked=0');
    }
    http_response_code(302);
    return;
}

if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $base = rtrim((string) Config::get('site_url'), '/');
    $urls = array_merge(['/', '/how-it-works', '/blog', '/about', '/support', '/privacy', '/terms'], array_map(fn ($s) => '/blog/' . $s, array_keys(View::posts())));
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $u) {
        echo '<url><loc>' . Http::e($base . ($u === '/' ? '/' : $u)) . '</loc></url>';
    }
    echo '</urlset>';
    return;
}

View::route($path);
