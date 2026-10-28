<?php
/** @var string $content @var string $title @var string $description @var string $site @var string $siteUrl @var string $path */
$fullTitle = !empty($home) ? $title : $title . ' | ' . $site;
$canonical = $siteUrl . ($path === '/' ? '' : $path);
$v = '4';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $e($fullTitle) ?></title>
<meta name="description" content="<?= $e($description) ?>">
<link rel="canonical" href="<?= $e($canonical) ?>">
<meta name="theme-color" content="#0b0b10">
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
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/icon-192.png">
<link rel="stylesheet" href="/assets/app.css?v=<?= $v ?>">
<?php if ($adsense !== ''): ?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= $e($adsense) ?>" crossorigin="anonymous"></script>
<?php endif; ?>
<?php if ($analytics !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $e($analytics) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= $e($analytics) ?>');</script>
<?php endif; ?>
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="logo" href="/"><span class="logo-mark" aria-hidden="true">@</span><?= $e($site) ?></a>
    <nav class="nav" aria-label="Main">
      <a href="/#inbox">Inbox</a>
      <a href="/#features">Features</a>
      <a href="/#premium">Premium</a>
      <a href="/#apps">Apps</a>
      <a href="/#faq">FAQ</a>
    </nav>
    <div class="topbar-actions">
      <button class="btn btn-ghost btn-sm" type="button" data-open="account-dialog">Account</button>
    </div>
  </div>
</header>

<main>
<?= $content ?>
</main>

<footer class="footer">
  <div class="wrap footer-grid">
    <div>
      <a class="logo" href="/"><span class="logo-mark" aria-hidden="true">@</span><?= $e($site) ?></a>
      <p class="muted">Free disposable email. No sign-up, no tracking of who you are.</p>
    </div>
    <div>
      <h4>Product</h4>
      <a href="/#features">Features</a>
      <a href="/#premium">Pro</a>
      <a href="/how-it-works">How it works</a>
      <a href="/blog">Blog</a>
    </div>
    <div>
      <h4>Company</h4>
      <a href="/about">About</a>
      <a href="/support">Support</a>
    </div>
    <div>
      <h4>Legal</h4>
      <a href="/privacy">Privacy</a>
      <a href="/terms">Terms</a>
      <a href="/delete-account">Delete account</a>
    </div>
  </div>
  <div class="wrap copyright muted">&copy; <?= date('Y') ?> <?= $e($site) ?>. All rights reserved.</div>
</footer>

<dialog id="account-dialog" class="dialog">
  <form method="dialog" class="dialog-close"><button class="icon-btn" aria-label="Close">&times;</button></form>
  <h3>Your account</h3>
  <p class="muted" id="account-status">No sign-up needed. This browser keeps your mailboxes.</p>
  <div class="stack">
    <button class="btn" type="button" id="btn-recovery">Show a recovery code</button>
    <div id="recovery-out" class="code-box" hidden></div>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="/assets/app.js?v=<?= $v ?>" defer></script>
</body>
</html>
