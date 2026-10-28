<?php
use Tms\View;

$faq = [
    ['What is temp mail?', 'Temp mail is a free, disposable email address you can use instead of your real one. Use it to sign up, get a verification code or download something, then let it expire. Your real inbox stays free of spam.'],
    ['How long do mailboxes and emails last?', 'A guest mailbox works for 5 days. If you save a recovery code or link a second device, your mailboxes last 30 days. Each email is deleted automatically 3 hours after it arrives.'],
    ['Can I use temp mail for verification codes?', 'Yes. Codes and confirmation links usually arrive within seconds. We detect the code and show a one-tap copy button next to the email.'],
    ['Is temp mail safe and legal?', 'Yes, using a disposable address to protect your privacy is legal. Do not use it for accounts you need to keep (banking, work, government) or for anything that breaks the law or another site\'s rules.'],
    ['Why does a website say my email is not allowed?', 'Some sites block known disposable domains. Try again with a new address, or pick a different domain if more than one is offered.'],
    ['Can I send email from a temporary address?', 'Not right now. Temp Mail Site is receive-only, which keeps it fast and free and stops spammers from abusing it.'],
    ['Can I create a custom email address?', 'Yes. Press "Change", type the name you want and pick a domain. If the name is free, it is yours until it expires.'],
    ['How is this different from 10 Minute Mail?', 'Your address does not vanish after 10 minutes. Mailboxes last days, you can keep several at once, open them on other devices and come back later with a recovery code.'],
    ['Is my data secure?', 'We never ask for your name, phone or real email. Email HTML is shown in a locked-down sandbox with scripts blocked, and everything is deleted on a timer.'],
    ['How do I delete my account?', 'Open Account and choose Delete account, or go to the Delete account page and enter your recovery code. Everything is removed at once.'],
    ['Can I receive attachments?', 'Yes. Attachments up to about 10 MB can be downloaded from the email view until the email expires.'],
    ['Is there a mobile app?', 'You can install Temp Mail Site on your phone straight from the browser: open the site and choose "Add to Home screen" or use the Install button below. It works like an app.'],
];
?>
<section class="hero" id="inbox">
  <div class="wrap">
    <p class="badge">Instant inbox &middot; works on phone and desktop</p>
    <h1>Free Temporary Email Address</h1>
    <ul class="ticks"><li>No sign-up</li><li>Auto-expires</li><li>Free forever</li></ul>

    <div class="app" id="app" data-poll="5">
      <div class="addr-card">
        <div class="addr-head">
          <span class="dot live" aria-hidden="true"></span>
          <span class="muted small" id="addr-expiry">Getting your address&hellip;</span>
          <select id="box-select" class="box-select" aria-label="Your addresses" hidden></select>
        </div>
        <div class="addr-row">
          <input id="addr" class="addr" readonly value="" aria-label="Your temporary email address" placeholder="loading&hellip;">
          <button class="btn btn-primary" id="btn-copy" type="button">Copy</button>
        </div>
        <div class="addr-actions">
          <button class="btn btn-ghost" id="btn-new" type="button">New address</button>
          <button class="btn btn-ghost" id="btn-change" type="button">Change</button>
          <button class="btn btn-ghost" id="btn-refresh" type="button">Refresh</button>
          <button class="btn btn-ghost danger" id="btn-delete" type="button">Delete</button>
        </div>
        <form id="custom-form" class="custom-form" hidden>
          <input name="name" placeholder="your-name" pattern="[A-Za-z0-9._-]{3,30}" required autocomplete="off">
          <span>@</span>
          <select name="domain" id="domain-select"></select>
          <button class="btn btn-primary" type="submit">Create</button>
        </form>
      </div>

      <div class="inbox">
        <div class="inbox-head">
          <h2>Inbox</h2>
          <span class="muted small" id="inbox-status">Waiting for email&hellip;</span>
        </div>
        <ul class="mail-list" id="mail-list" aria-live="polite"></ul>
        <div class="empty" id="empty">
          <div class="spinner" aria-hidden="true"></div>
          <p>Your inbox is empty</p>
          <p class="muted small">New email shows up here automatically within seconds.</p>
        </div>
      </div>

      <article class="reader" id="reader" hidden>
        <header class="reader-head">
          <button class="btn btn-ghost btn-sm" id="btn-back" type="button">&larr; Back</button>
          <div class="reader-actions">
            <button class="btn btn-ghost btn-sm danger" id="btn-del-msg" type="button">Delete</button>
          </div>
        </header>
        <h3 id="r-subject"></h3>
        <div class="reader-from"><img id="r-icon" class="favicon" alt="" width="32" height="32"><div><strong id="r-from"></strong><div class="muted small" id="r-addr"></div></div><span class="muted small" id="r-time"></span></div>
        <p class="muted small delivery" id="r-delivery" hidden></p>
        <div class="otp-bar" id="r-otp" hidden><span>Code</span><strong id="r-otp-code"></strong><button class="btn btn-primary btn-sm" type="button" id="btn-copy-otp">Copy code</button></div>
        <div class="otp-bar" id="r-link" hidden><span>Link</span><a id="r-link-a" target="_blank" rel="noopener noreferrer nofollow">Open confirmation link</a></div>
        <div class="tabs" id="r-tabs" hidden><button type="button" data-view="html" class="on">HTML</button><button type="button" data-view="text">Text</button></div>
        <iframe id="r-html" class="mail-frame" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" title="Email content"></iframe>
        <pre id="r-text" class="mail-text" hidden></pre>
        <ul class="attachments" id="r-atts"></ul>
      </article>
    </div>
    <p class="muted small center">Use it anywhere you sign up: <a href="/blog/temp-mail-for-discord">Discord</a>, <a href="/blog/temp-mail-for-instagram">Instagram</a>, <a href="/blog/temp-mail-for-chatgpt">ChatGPT</a>, <a href="/blog/temp-mail-for-steam">Steam</a></p>
  </div>
</section>

<div class="wrap"><?= View::ad($adsense, $adSlot) ?></div>

<section class="section" id="features">
  <div class="wrap">
    <h2 class="section-title">Everything a temporary inbox should be</h2>
    <div class="grid3">
      <div class="card"><div class="card-icon">&#9889;</div><h3>Instant address, no sign-up</h3><p>A fresh address is ready the moment the page opens. Copy it with one tap, or make a new one any time.</p></div>
      <div class="card"><div class="card-icon">&#128229;</div><h3>Real-time inbox</h3><p>Email appears within seconds without reloading. Verification codes get a one-tap copy button.</p></div>
      <div class="card"><div class="card-icon">&#128274;</div><h3>Private by design</h3><p>No personal details needed. Email HTML runs in a sandbox with scripts blocked, and mail is deleted after 3 hours.</p></div>
      <div class="card"><div class="card-icon">&#9999;&#65039;</div><h3>Custom addresses</h3><p>Pick your own name on any available domain, or keep several addresses side by side.</p></div>
      <div class="card"><div class="card-icon">&#128241;</div><h3>Synced across devices</h3><p>Scan a QR code to open the same inbox on your phone, or restore it later with a recovery code.</p></div>
      <div class="card"><div class="card-icon">&#128206;</div><h3>Attachments</h3><p>Receive files and images and download them safely from the email view.</p></div>
    </div>
  </div>
</section>

<section class="section alt" id="premium">
  <div class="wrap">
    <h2 class="section-title">Pro is coming</h2>
    <p class="center muted">Everything above is free. Pro will add more for people who use temp mail every day.</p>
    <div class="grid3">
      <div class="card"><h3>Longer storage</h3><p>Keep email for 30 days instead of 3 hours, and mailboxes for up to 6 months.</p></div>
      <div class="card"><h3>Email forwarding</h3><p>Forward any mailbox to your real inbox, with an on/off switch per address.</p></div>
      <div class="card"><h3>Your own domain</h3><p>Add one MX record and receive mail on your own domain.</p></div>
      <div class="card"><h3>Unlimited mailboxes</h3><p>No daily limit on new addresses.</p></div>
      <div class="card"><h3>No ads</h3><p>A clean inbox with nothing else on the page.</p></div>
      <div class="card"><h3>API for AI tools</h3><p>Let your scripts and AI agents create inboxes and read codes.</p></div>
    </div>
  </div>
</section>

<section class="section" id="how">
  <div class="wrap">
    <h2 class="section-title">Three steps. No sign-up.</h2>
    <ol class="steps">
      <li><h3>Copy your address</h3><p>A random address is made for you when the page loads. Tap Copy.</p></li>
      <li><h3>Use it anywhere</h3><p>Paste it into any sign-up or verification form.</p></li>
      <li><h3>Read it here, then let it expire</h3><p>Codes and links show up in seconds. Guest mailboxes expire after 5 days (30 with a recovery code).</p></li>
    </ol>
    <p class="center"><a class="btn" href="/how-it-works">Read the full guide</a></p>
  </div>
</section>

<section class="section alt" id="apps">
  <div class="wrap">
    <h2 class="section-title">Your inbox, everywhere</h2>
    <div class="grid2">
      <div class="card"><h3>Install on your phone</h3><p>Temp Mail Site installs straight from the browser on Android and iPhone. No app store needed.</p><button class="btn btn-primary" id="btn-install" type="button">Install app</button><p class="muted small" id="install-hint"></p></div>
      <div class="card"><h3>Link a device with a QR code</h3><p>Open Account, choose "Link another device" and scan the code with your phone. Both devices see the same inbox.</p><button class="btn" type="button" data-open="account-dialog">Link a device</button></div>
    </div>
  </div>
</section>

<section class="section" id="guides">
  <div class="wrap">
    <h2 class="section-title">Guides &amp; tips</h2>
    <div class="grid3">
      <?php foreach (array_slice(View::posts(), 0, 6, true) as $slug => $post): ?>
        <a class="card card-link" href="/blog/<?= $e($slug) ?>"><h3><?= $e($post['title']) ?></h3><p><?= $e($post['description']) ?></p></a>
      <?php endforeach; ?>
    </div>
    <p class="center"><a class="btn" href="/blog">All guides</a></p>
  </div>
</section>

<div class="wrap"><?= View::ad($adsense, $adSlot) ?></div>

<section class="section alt" id="faq">
  <div class="wrap narrow">
    <h2 class="section-title">Frequently asked questions</h2>
    <?php foreach ($faq as [$q, $a]): ?>
      <details class="faq"><summary><?= $e($q) ?></summary><p><?= $e($a) ?></p></details>
    <?php endforeach; ?>
    <p class="center muted">Still have questions? <a href="/support">Contact support</a></p>
  </div>
</section>

<section class="section cta">
  <div class="wrap center">
    <h2>Get a free temporary email in one click</h2>
    <a class="btn btn-primary btn-lg" href="#inbox">Get my address</a>
  </div>
</section>

<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
