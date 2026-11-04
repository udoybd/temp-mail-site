<?php
use Tms\View;

/** @var array $cs */
$ticks = array_filter(array_map('trim', explode(',', $cs['hero_ticks'])));
$check = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4.5 12.5 4.5 4.5L19.5 6.5"/></svg>';
$apps = [
    ['Discord', 'discord.com', '/blog/temp-mail-for-discord'],
    ['Instagram', 'instagram.com', '/blog/temp-mail-for-instagram'],
    ['ChatGPT', 'chatgpt.com', '/blog/temp-mail-for-chatgpt'],
    ['Steam', 'steampowered.com', '/blog/temp-mail-for-steam'],
];
?>
<section class="hero" id="inbox">
  <div class="wrap">
    <p class="badge"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 2 4 13.5h7L10 22l10-12h-7l.5-8Z"/></svg><span><?= $e($cs['hero_badge']) ?></span></p>
    <h1><?= $e($cs['hero_title']) ?></h1>
    <?php if ($ticks): ?><ul class="ticks"><?php foreach ($ticks as $tick): ?><li><?= $check ?><?= $e($tick) ?></li><?php endforeach; ?></ul><?php endif; ?>

    <div class="app" id="app" data-poll="5">
      <div class="addr-card">
        <div class="addr-head">
          <span class="dot live" aria-hidden="true"></span>
          <span class="addr-expiry" id="addr-expiry">Getting your address&hellip;</span>
          <span class="live-pill" id="live-pill"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="1.8"/><path d="M8.5 8.5a5 5 0 0 0 0 7M15.5 8.5a5 5 0 0 1 0 7M5.6 5.6a9 9 0 0 0 0 12.8M18.4 5.6a9 9 0 0 1 0 12.8"/></svg>Live Inbox</span>
        </div>
        <div class="addr-row">
          <label class="addr-field">
            <svg class="addr-icon" viewBox="0 0 32 24" aria-hidden="true"><rect width="32" height="24" rx="5" fill="#2f80ed"/><path d="M6.5 7 16 14l9.5-7M6.5 18l6.5-5.5M25.5 18 19 12.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <input id="addr" class="addr" readonly value="" aria-label="Your temporary email address" placeholder="loading&hellip;" translate="no">
          </label>
          <button class="btn btn-primary btn-copy" id="btn-copy" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8.5" y="8.5" width="12" height="12" rx="2.5"/><path d="M15.5 8.5V6a2.5 2.5 0 0 0-2.5-2.5H6A2.5 2.5 0 0 0 3.5 6v7A2.5 2.5 0 0 0 6 15.5h2.5"/></svg>Copy</button>
        </div>
        <div class="addr-actions">
          <button class="btn btn-ghost" id="btn-new" type="button"><svg class="i-plus" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5v17M3.5 12h17"/></svg>New Address</button>
          <button class="btn btn-ghost" id="btn-change" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.7-5.6L20 8.5M20 3.5v5h-5M20 12a8 8 0 0 1-13.7 5.6L4 15.5M4 20.5v-5h5"/></svg>Change</button>
          <button class="btn btn-ghost" id="btn-refresh" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.3-5.7L20 8.5M20 3.5v5h-5"/></svg>Refresh</button>
          <button class="btn btn-danger" id="btn-delete" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6.5h16M9.5 6.5V4.5h5v2M6.5 6.5l.8 13a1.5 1.5 0 0 0 1.5 1.4h6.4a1.5 1.5 0 0 0 1.5-1.4l.8-13M10 10.5v6.5M14 10.5v6.5"/></svg>Delete</button>
        </div>
        <form id="custom-form" class="custom-form" hidden>
          <input name="name" placeholder="your-name" pattern="[A-Za-z0-9._\-]{3,30}" required autocomplete="off" translate="no">
          <span>@</span>
          <select name="domain" id="domain-select" translate="no"></select>
          <button class="btn btn-primary" type="submit">Create</button>
        </form>
      </div>

      <div class="inbox">
        <div class="inbox-head">
          <div class="inbox-tab"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="m3 6.5 9 6.5 9-6.5"/></svg><span>Inbox (<span id="inbox-count">0</span>)</span></div>
          <span class="inbox-status" id="inbox-status">Waiting for email&hellip;</span>
          <label class="switch"><span>Auto Refresh</span><input type="checkbox" id="auto-refresh" checked><span class="slider" aria-hidden="true"></span></label>
        </div>
        <div class="inbox-body">
          <ul class="mail-list" id="mail-list" aria-live="polite"></ul>
          <div class="empty" id="empty">
            <svg class="empty-art" viewBox="0 0 120 90" aria-hidden="true">
              <ellipse cx="62" cy="52" rx="54" ry="36" fill="#eef3ff"/>
              <path d="M22 14l8 6M30 6l3 10M14 24l9 2" stroke="#5b3df5" stroke-width="3" stroke-linecap="round"/>
              <path d="M36 44 62 24l26 20v30a4 4 0 0 1-4 4H40a4 4 0 0 1-4-4Z" fill="#4a8ff7"/>
              <rect x="44" y="20" width="36" height="34" rx="3" fill="#fff"/>
              <path d="M50 30h24M50 37h24M50 44h16" stroke="#c9d6ef" stroke-width="3" stroke-linecap="round"/>
              <path d="M36 46l26 18 26-18v28a4 4 0 0 1-4 4H40a4 4 0 0 1-4-4Z" fill="#69a5fb"/>
              <path d="M36 78l22-17M88 78 66 61" stroke="#4a8ff7" stroke-width="2.5"/>
            </svg>
            <p class="empty-title">Your inbox is empty</p>
            <p class="empty-sub">New email shows up here automatically within seconds</p>
          </div>
        </div>
      </div>

      <article class="reader" id="reader" hidden>
        <header class="reader-head">
          <button class="btn btn-ghost btn-sm" id="btn-back" type="button">&larr; Back</button>
          <div class="reader-actions">
            <button class="btn btn-danger btn-sm" id="btn-del-msg" type="button">Delete</button>
          </div>
        </header>
        <h3 id="r-subject"></h3>
        <div class="reader-from"><img id="r-icon" class="favicon" alt="" width="32" height="32"><div><strong id="r-from"></strong><div class="muted small" id="r-addr" translate="no"></div></div><span class="muted small" id="r-time"></span></div>
        <p class="muted small delivery" id="r-delivery" hidden></p>
        <div class="otp-bar" id="r-otp" hidden><span>Code</span><strong id="r-otp-code" translate="no"></strong><button class="btn btn-primary btn-sm" type="button" id="btn-copy-otp">Copy code</button></div>
        <div class="otp-bar" id="r-link" hidden><span>Link</span><a id="r-link-a" target="_blank" rel="noopener noreferrer nofollow">Open confirmation link</a></div>
        <div class="tabs" id="r-tabs" hidden><button type="button" data-view="html" class="on">HTML</button><button type="button" data-view="text">Text</button></div>
        <iframe id="r-html" class="mail-frame" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" title="Email content"></iframe>
        <pre id="r-text" class="mail-text" hidden translate="no"></pre>
        <ul class="attachments" id="r-atts"></ul>
      </article>

      <div class="use-row">
        <span>Use it anywhere you sign-up:</span>
        <?php foreach ($apps as [$name, $domain, $href]): ?>
          <a class="app-chip" href="<?= $href ?>"><img src="https://www.google.com/s2/favicons?sz=64&amp;domain=<?= $domain ?>" alt="" width="20" height="20" loading="lazy" referrerpolicy="no-referrer"><?= $name ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="perks" id="features">
  <div class="wrap perks-row">
    <div class="perk"><span class="perk-icon pi-blue"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8 4.5 5.6v5.8c0 4.7 3.2 8.6 7.5 9.8 4.3-1.2 7.5-5.1 7.5-9.8V5.6L12 2.8Z"/><path d="m8.6 12 2.4 2.4 4.4-4.6"/></svg></span><h3>Keep Your Privacy</h3><p>Stay anonymous and protect your real email.</p></div>
    <div class="perk"><span class="perk-icon pi-violet"><svg viewBox="0 0 24 24" aria-hidden="true"><path class="fill" d="M13.5 2 4 13.5h7L10 22l10-12h-7l.5-8Z"/></svg></span><h3>Instant Access</h3><p>Get a temporary email address in one click.</p></div>
    <div class="perk"><span class="perk-icon pi-green"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c-2-2.6-3.6-4-5.6-4a4 4 0 0 0 0 8c2 0 3.6-1.4 5.6-4Zm0 0c2 2.6 3.6 4 5.6 4a4 4 0 0 0 0-8c-2 0-3.6 1.4-5.6 4Z"/></svg></span><h3>Unlimited Emails</h3><p>Generate as many emails as you need.</p></div>
    <div class="perk"><span class="perk-icon pi-red"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6.5h16M9.5 6.5V4.5h5v2M6.5 6.5l.8 13a1.5 1.5 0 0 0 1.5 1.4h6.4a1.5 1.5 0 0 0 1.5-1.4l.8-13M10 10.5v6.5M14 10.5v6.5"/></svg></span><h3>Auto Delete</h3><p>Emails are automatically deleted after expiration.</p></div>
  </div>
</section>

<div class="wrap"><?= View::ad($adsense, $adSlot) ?></div>
