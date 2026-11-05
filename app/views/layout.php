<?php
/** @var string $content @var string $title @var string $description @var string $site @var string $siteUrl @var string $path @var array $cs */
$fullTitle = !empty($home) ? $title : $site . ' | ' . $title;
$canonical = $siteUrl . ($path === '/' ? '' : $path);
$v = '16';
$nav = ['/' => 'Home', '/features' => 'Features', '/faq' => 'FAQ', '/blog' => 'Blog', '/contact' => 'Contact'];
$isOn = fn (string $href) => $href === '/' ? $path === '/' : ($path === $href || str_starts_with($path, $href . '/'));
$logo = fn (string $id) => '<svg class="logo-mark" viewBox="0 0 40 32" aria-hidden="true"><defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3b8df6"/><stop offset="1" stop-color="#1f5fe0"/></linearGradient></defs><rect width="40" height="32" rx="7" fill="url(#' . $id . ')"/><path d="M8.5 9.5 20 18l11.5-8.5M8.5 23l8.2-7.2M31.5 23l-8.2-7.2" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$brand = '<span class="logo-text">Bengal<span class="logo-accent">.email</span></span>';
$langs = ['en' => 'English', 'bn' => 'বাংলা', 'hi' => 'हिन्दी', 'ur' => 'اردو', 'ar' => 'العربية', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch', 'pt' => 'Português', 'ru' => 'Русский', 'tr' => 'Türkçe', 'id' => 'Bahasa Indonesia', 'vi' => 'Tiếng Việt', 'zh-CN' => '中文', 'ja' => '日本語'];
$langSelect = function (string $cls) use ($langs, $e): string {
    $out = '<label class="lang ' . $cls . '"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M2.5 12h19M12 2.5c2.6 2.8 3.9 6 3.9 9.5s-1.3 6.7-3.9 9.5c-2.6-2.8-3.9-6-3.9-9.5S9.4 5.3 12 2.5Z"/></svg>'
        . '<select class="lang-select" aria-label="Language">';
    foreach ($langs as $code => $name) {
        $out .= '<option value="' . $e($code) . '">' . $e($name) . '</option>';
    }
    return $out . '</select><svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></label>';
};
$social = [
    'social_facebook' => ['Facebook', 'fb', '<path fill="#fff" d="M13.4 21v-7.6h2.6l.4-3h-3V8.5c0-.9.3-1.5 1.5-1.5h1.6V4.3c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.7v3h2.6V21h3.1Z"/>'],
    'social_x' => ['X', 'x', '<path fill="#fff" d="M17.2 4h2.8l-6.1 7 7.2 9h-5.6l-4.4-5.7L6 20H3.2l6.5-7.5L2.8 4h5.7l4 5.2L17.2 4Zm-1 14.4h1.5L7.9 5.5H6.3l9.9 12.9Z"/>'],
    'social_telegram' => ['Telegram', 'tg', '<path fill="#fff" d="M20.7 4.3 3.4 11c-1.2.5-1.2 1.2-.2 1.5l4.4 1.4 1.7 5.2c.2.6.1.8.7.8.5 0 .7-.2 1-.5l2.1-2.1 4.5 3.3c.8.5 1.4.2 1.6-.8l2.9-13.7c.3-1.2-.5-1.8-1.4-1.4ZM8.6 13.6l9.3-5.9c.5-.3.9-.1.5.2l-7.9 7.2-.3 3.4-1.6-4.9Z"/>'],
    'social_youtube' => ['YouTube', 'yt', '<path fill="#fff" d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8ZM10 15V9l5.2 3L10 15Z"/>'],
];
$socialOn = array_filter($social, fn ($k) => trim($cs[$k] ?? '') !== '', ARRAY_FILTER_USE_KEY);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $e($fullTitle) ?></title>
<meta name="description" content="<?= $e($description) ?>">
<link rel="canonical" href="<?= $e($canonical) ?>">
<meta name="theme-color" content="#f4f7fd">
<meta name="robots" content="index, follow">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= $e($site) ?>">
<meta property="og:title" content="<?= $e($fullTitle) ?>">
<meta property="og:description" content="<?= $e($description) ?>">
<meta property="og:url" content="<?= $e($canonical) ?>">
<meta property="og:image" content="<?= $e($siteUrl) ?>/assets/og.png">
<meta name="twitter:card" content="summary_large_image">
<meta name="apple-mobile-web-app-title" content="<?= $e($site) ?>">
<meta name="application-name" content="<?= $e($site) ?>">
<meta name="monetag" content="79fa10b9e39bcd94abc358fb9abe0c4d">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="/assets/app.css?v=<?= $v ?>">
<?php if (!empty($home)): ?><link rel="stylesheet" href="/assets/home.css?v=<?= $v ?>">
<?php endif; ?>
<script>try{if(localStorage.getItem('tms_theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
<?php if ($adsense !== ''): ?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= $e($adsense) ?>" crossorigin="anonymous"></script>
<?php endif; ?>
<?php if ($analytics !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $e($analytics) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= $e($analytics) ?>');</script>
<?php endif; ?>
<?= $cs['header_code'] ?? '' ?>
</head>
<body>
<div class="bg-waves" aria-hidden="true"></div>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="logo" href="/" translate="no"><?= $logo('lg-h') ?><?= $brand ?></a>
    <nav class="nav" id="nav" aria-label="Main">
      <?php foreach ($nav as $href => $label): ?>
        <a href="<?= $href ?>"<?= $isOn($href) ? ' class="on" aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
      <?= $langSelect('lang-mobile') ?>
    </nav>
    <div class="topbar-actions">
      <span class="nav-sep" aria-hidden="true"></span>
      <?= $langSelect('lang-desktop') ?>
      <button class="icon-toggle" id="theme-toggle" type="button" aria-label="Switch dark mode">
        <svg class="i-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/></svg>
        <svg class="i-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2.2M12 19.8V22M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M2 12h2.2M19.8 12H22M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6"/></svg>
      </button>
      <button class="btn btn-primary btn-account" type="button" data-open="account-dialog">Account</button>
      <button class="menu-btn" id="menu-btn" type="button" aria-label="Menu" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>

<main>
<?= $content ?>
</main>

<footer class="footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <a class="logo" href="/" translate="no"><?= $logo('lg-f') ?><?= $brand ?></a>
      <p><?= $e($cs['footer_text']) ?></p>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <a href="/">Home</a>
      <a href="/#inbox">Inbox</a>
      <a href="/features">Features</a>
      <a href="/blog">Blog</a>
    </div>
    <div class="footer-col">
      <h4>Support</h4>
      <a href="/faq">FAQ</a>
      <a href="/contact">Contact</a>
      <a href="/about">About us</a>
      <a href="/how-it-works">How it works</a>
    </div>
    <div class="footer-col footer-social"<?= $socialOn ? '' : ' hidden' ?>>
      <h4>Follow Us</h4>
      <div class="social">
        <?php foreach ($socialOn as $key => [$label, $cls, $svg]): ?>
          <a class="soc soc-<?= $cls ?>" href="<?= $e($cs[$key]) ?>" target="_blank" rel="noopener" aria-label="<?= $label ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $svg ?></svg></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="wrap footer-bottom">
    <span>&copy; <?= date('Y') ?> <span translate="no"><?= $e($site) ?></span>. All rights reserved.</span>
    <nav aria-label="Legal"><a href="/about">About</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a><a href="/support">Help</a><a href="/delete-account">Delete account</a></nav>
  </div>
</footer>

<dialog id="account-dialog" class="dialog">
  <form method="dialog" class="dialog-close"><button class="icon-btn" aria-label="Close">&times;</button></form>
  <h3>Your account</h3>
  <p class="muted" id="account-status">No sign-up needed. This browser keeps your mailboxes.</p>
  <div class="box-list-wrap" id="box-list-wrap" hidden>
    <h4>Your addresses</h4>
    <ul class="box-list" id="box-list" translate="no"></ul>
    <p class="muted small">Tap an address to open its inbox. Addresses you did not delete stay yours for 24 hours. A deleted name can be made again at once with Change.</p>
  </div>
  <div class="stack">
    <button class="btn" type="button" id="btn-recovery">Show a recovery code</button>
    <div id="recovery-out" class="code-box" hidden translate="no"></div>
    <button class="btn" type="button" id="btn-link">Link another device (QR)</button>
    <div id="link-out" class="qr-box" hidden><div id="qr"></div><p class="muted small">Scan with your phone camera within 10 minutes. <a id="link-url" href="#">Copy link</a></p></div>
    <details>
      <summary>Restore with a recovery code</summary>
      <form id="restore-form" class="row">
        <input name="code" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" required>
        <button class="btn" type="submit">Restore</button>
      </form>
    </details>
    <a class="danger-link" href="/delete-account">Delete account</a>
  </div>
</dialog>

<div id="toast" class="toast" role="status" aria-live="polite"></div>
<div id="google_translate_element" hidden></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="/assets/app.js?v=<?= $v ?>" defer></script>
<?= $cs['footer_code'] ?? '' ?>
</body>
</html>
