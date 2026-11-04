<?php
/** @var string $tab @var string $site @var string $csrf @var DateTimeZone $tz */
$t = fn ($ts) => $ts ? (new DateTimeImmutable('@' . (int) $ts))->setTimezone($tz)->format('d M, h:i A') : '-';
$n = fn ($v) => number_format((int) $v);
$size = function (int $bytes): string {
    foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
        if ($bytes < 1024 || $unit === 'GB') {
            return round($bytes, 1) . ' ' . $unit;
        }
        $bytes /= 1024;
    }
    return '';
};
$labels = ['dashboard' => 'Dashboard', 'blog' => 'Blog', 'pages' => 'Pages', 'faq' => 'FAQ', 'messages' => 'Messages' . ($unread ? ' (' . $unread . ')' : ''), 'settings' => 'Settings', 'domains' => 'Domains', 'mail' => 'Mail', 'logs' => 'Logs'];
$hidden = fn (string $k, $v) => '<input type="hidden" name="' . $e($k) . '" value="' . $e((string) $v) . '">';
$logoSvg = '<svg class="logo-mark" viewBox="0 0 40 32" aria-hidden="true"><rect width="40" height="32" rx="7" fill="#2f80ed"/><path d="M8.5 9.5 20 18l11.5-8.5M8.5 23l8.2-7.2M31.5 23l-8.2-7.2" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$form = fn (string $action, string $label, string $domain = '', string $class = 'btn btn-sm') =>
    '<form method="post" class="inline"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="action" value="' . $e($action) . '">'
    . ($domain !== '' ? '<input type="hidden" name="domain" value="' . $e($domain) . '">' : '')
    . '<button class="' . $e($class) . '">' . $e($label) . '</button></form>';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin | <?= $e($site) ?></title>
<link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="/assets/app.css?v=6">
<link rel="stylesheet" href="/assets/admin.css?v=2">
<script src="/assets/admin.js?v=1" defer></script>
</head>
<body>
<div class="admin">
<?php if ($tab === 'setup' || $tab === 'login'): ?>
  <div class="auth">
    <a class="logo" href="/"><?= $logoSvg ?><span class="logo-text">Temp<span class="logo-accent">Mail</span> Site</span></a>
    <?php if (!empty($error)): ?><div class="msg err"><?= $e($error) ?></div><?php endif; ?>
    <form method="post" class="box" style="margin-top:16px">
      <?php if ($tab === 'setup'): ?>
        <h2 style="margin-top:0">Set your admin password</h2>
        <p class="muted small">One time only. In cPanel open File Manager, go to <code>temp-mail-site/storage</code>, open <code>admin-setup-code.txt</code> and copy the 8-character code.</p>
        <label>Setup code<input name="code" required autocomplete="off" autocapitalize="characters"></label>
        <label>New password (8+ characters)<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Type it again<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
        <button class="btn btn-primary">Save password</button>
      <?php else: ?>
        <h2 style="margin-top:0">Log in</h2>
        <input type="hidden" name="action" value="login">
        <label>Password<input type="password" name="password" required autofocus autocomplete="current-password"></label>
        <button class="btn btn-primary">Log in</button>
        <p class="muted small">Forgot it? Delete <code>temp-mail-site/storage/admin.php</code> in cPanel File Manager, then open this page again.</p>
      <?php endif; ?>
    </form>
  </div>
<?php else: ?>
  <div class="a-top">
    <a class="logo" href="/"><?= $logoSvg ?><span class="logo-text">Temp<span class="logo-accent">Mail</span> Site</span></a>
    <span class="pill">admin</span>
    <span style="margin-left:auto"><?= $form('logout', 'Log out', '', 'btn btn-ghost btn-sm') ?></span>
  </div>
  <nav class="a-tabs">
    <?php foreach ($labels as $key => $label): ?>
      <a href="/admin?tab=<?= $key ?>" class="<?= $tab === $key ? 'on' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!empty($flash)): ?><div class="msg ok"><?= $e($flash) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="msg err"><?= $e($error) ?></div><?php endif; ?>

  <?php if ($tab === 'dashboard'): ?>
    <h2>Right now</h2>
    <div class="tiles">
      <div class="tile"><div class="k">Live addresses</div><div class="v"><?= $n($live['mailboxes']) ?></div></div>
      <div class="tile"><div class="k">Emails stored</div><div class="v"><?= $n($live['emails']) ?></div><div class="s">kept 3 hours each</div></div>
      <div class="tile"><div class="k">Visitors (accounts)</div><div class="v"><?= $n($live['accounts']) ?></div><div class="s"><?= $n($live['online']) ?> active in last 10 min</div></div>
      <div class="tile"><div class="k">Storage used</div><div class="v"><?= $size($disk) ?></div></div>
    </div>

    <h2>Created</h2>
    <div class="tbl-wrap"><table>
      <tr><th></th><th class="num">Today</th><th class="num">7 days</th><th class="num">30 days</th><th class="num">All time</th></tr>
      <tr><td>Addresses created</td><?php foreach ($counts['mailboxes'] as $v): ?><td class="num"><?= $n($v) ?></td><?php endforeach; ?></tr>
      <tr><td>Emails received</td><?php foreach ($counts['emails'] as $v): ?><td class="num"><?= $n($v) ?></td><?php endforeach; ?></tr>
      <tr><td>Emails to unknown address (dropped)</td><?php foreach ($counts['dropped'] as $v): ?><td class="num"><?= $n($v) ?></td><?php endforeach; ?></tr>
    </table></div>

    <h2>Last 14 days</h2>
    <?php $max = max(1, ...array_values(array_map(fn ($d) => max($d['mailboxes'], $d['emails']), $daily))); ?>
    <div class="bars">
      <?php foreach ($daily as $day => $d): ?>
        <div class="col" title="<?= $e($day) ?>: <?= $d['mailboxes'] ?> addresses, <?= $d['emails'] ?> emails">
          <div style="display:flex;gap:1px;align-items:flex-end;width:100%;height:100%">
            <div class="b mb" style="height:<?= round($d['mailboxes'] / $max * 100) ?>%"></div>
            <div class="b em" style="height:<?= round($d['emails'] / $max * 100) ?>%"></div>
          </div>
          <span class="d"><?= substr($day, 8) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="legend"><span><i style="background:var(--accent)"></i>Addresses</span><span><i style="background:var(--accent2)"></i>Emails</span></div>

    <h2>Health</h2>
    <div class="tiles">
      <div class="tile"><div class="k">Delivery time (24h avg)</div>
        <?php if ((int) ($speed['n'] ?? 0) > 0): ?>
          <div class="v"><?= round((float) $speed['total']) ?>s</div><div class="s">sender &rarr; inbox. Our server part: <?= round((float) $speed['ours']) ?>s. <?= $n($speed['n']) ?> emails.</div>
        <?php else: ?>
          <div class="v">-</div><div class="s">No emails in the last 24 hours</div>
        <?php endif; ?>
      </div>
      <div class="tile"><div class="k">Cleanup cron</div>
        <?php if ($cron && $cron > time() - 3600): ?>
          <div class="v" style="color:var(--accent2)">OK</div><div class="s">last run <?= $t($cron) ?></div>
        <?php else: ?>
          <div class="v" style="color:var(--danger)">Not running</div><div class="s"><?= $cron ? 'last run ' . $t($cron) : 'never ran' ?>. Add a cPanel Cron Job every 10 min: <code>php <?= $e(TMS_ROOT) ?>/bin/cron.php</code></div>
        <?php endif; ?>
      </div>
    </div>

    <h2>Change password</h2>
    <form method="post" class="box">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="change-password">
      <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
      <label>New password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <label>Type it again<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
      <button class="btn">Change password</button>
    </form>

  <?php elseif ($tab === 'blog'): ?>
    <?php if ($post === null): ?>
      <div class="a-bar"><h2>Blog posts</h2><a class="btn btn-primary" href="/admin?tab=blog&amp;edit=new">+ New post</a></div>
      <div class="tbl-wrap"><table>
        <tr><th>Title</th><th>Status</th><th>Date</th><th>Updated</th><th></th></tr>
        <?php foreach ($posts as $p): ?>
          <tr>
            <td><a href="/admin?tab=blog&amp;edit=<?= (int) $p['id'] ?>"><strong><?= $e($p['title']) ?></strong></a><div class="muted small">/blog/<?= $e($p['slug']) ?></div></td>
            <td><?php if ($p['status'] !== 'published'): ?><span class="pill off">draft</span><?php elseif ($p['published_at'] > time()): ?><span class="pill">scheduled</span><?php else: ?><span class="pill on">published</span><?php endif; ?></td>
            <td class="small" style="white-space:nowrap"><?= $t($p['published_at']) ?></td>
            <td class="small" style="white-space:nowrap"><?= $t($p['updated_at']) ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-sm" href="/admin?tab=blog&amp;edit=<?= (int) $p['id'] ?>">Edit</a>
              <?php if ($p['status'] === 'published'): ?><a class="btn btn-sm" href="/blog/<?= $e($p['slug']) ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
              <form method="post" class="inline" data-confirm="Delete this post for good?"><?= $hidden('csrf', $csrf) . $hidden('action', 'delete-post') . $hidden('tab', 'blog') . $hidden('id', $p['id']) ?><button class="btn btn-sm danger">Delete</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$posts): ?><tr><td colspan="5" class="muted">No posts yet. Press "New post".</td></tr><?php endif; ?>
      </table></div>
    <?php else: ?>
      <div class="a-bar"><h2><?= $post['id'] ? 'Edit post' : 'New post' ?></h2><a class="btn btn-sm" href="/admin?tab=blog">&larr; All posts</a>
        <?php if ($post['id'] && $post['status'] === 'published'): ?><a class="btn btn-sm" href="/blog/<?= $e($post['slug']) ?>" target="_blank" rel="noopener">View on site</a><?php endif; ?></div>
      <form method="post" enctype="multipart/form-data" class="box editor-form">
        <?= $hidden('csrf', $csrf) . $hidden('action', 'save-post') . $hidden('tab', 'blog') . $hidden('id', $post['id']) . $hidden('cover', $post['cover']) ?>
        <label>Title<input name="title" id="f-title" value="<?= $e($post['title']) ?>" required maxlength="200"></label>
        <label>Link (slug) <span class="muted small">jamaluddin.info/blog/<b id="slug-preview"><?= $e($post['slug']) ?></b></span><input name="slug" id="f-slug" value="<?= $e($post['slug']) ?>" placeholder="made from the title if empty" maxlength="120" data-auto="<?= $post['id'] ? '0' : '1' ?>"></label>
        <label>Short description <span class="muted small">(shown on the blog list and in Google)</span><input name="description" value="<?= $e($post['description']) ?>" maxlength="300"></label>
        <div class="grid-2">
          <label>Status<select name="status"><option value="published"<?= $post['status'] === 'published' ? ' selected' : '' ?>>Published (visible)</option><option value="draft"<?= $post['status'] !== 'published' ? ' selected' : '' ?>>Draft (hidden)</option></select></label>
          <label>Publish date<input type="datetime-local" name="date" value="<?= $e((new DateTimeImmutable('@' . (int) $post['published_at']))->setTimezone($tz)->format('Y-m-d\TH:i')) ?>"></label>
        </div>
        <div class="cover-row">
          <?php if ($post['cover'] !== ''): ?><img src="<?= $e($post['cover']) ?>" alt="" class="cover-thumb"><label class="check"><input type="checkbox" name="remove_cover" value="1"> Remove cover image</label><?php endif; ?>
          <label>Cover image <span class="muted small">(JPG/PNG/WebP, optional)</span><input type="file" name="cover_file" accept="image/*"></label>
        </div>
        <?php $editorName = 'body'; $editorValue = $post['body']; require __DIR__ . '/admin-editor.php'; ?>
        <div class="a-bar"><button class="btn btn-primary">Save post</button></div>
      </form>
      <?php if ($post['id']): ?>
        <form method="post" data-confirm="Delete this post for good?"><?= $hidden('csrf', $csrf) . $hidden('action', 'delete-post') . $hidden('tab', 'blog') . $hidden('id', $post['id']) ?><button class="btn btn-sm danger">Delete this post</button></form>
      <?php endif; ?>
    <?php endif; ?>

  <?php elseif ($tab === 'pages'): ?>
    <?php if ($page === null): ?>
      <h2>Site pages</h2>
      <p class="muted small">Change the text of any page. "Reset" puts the original text back.</p>
      <div class="tbl-wrap"><table>
        <tr><th>Page</th><th>Address</th><th>Status</th><th></th></tr>
        <?php foreach (Tms\Content::PAGES as $slug => [$ptitle]): ?>
          <tr>
            <td><strong><?= $e($ptitle) ?></strong></td>
            <td class="small"><a href="/<?= $e($slug) ?>" target="_blank" rel="noopener">/<?= $e($slug) ?></a></td>
            <td class="small"><?= isset($custom[$slug]) ? '<span class="pill on">edited ' . $e($t($custom[$slug])) . '</span>' : '<span class="pill">original</span>' ?></td>
            <td><a class="btn btn-sm" href="/admin?tab=pages&amp;edit=<?= $e($slug) ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </table></div>
      <p class="muted small">Home page title, footer text and social links are under <a href="/admin?tab=settings">Settings</a>. FAQ questions are under <a href="/admin?tab=faq">FAQ</a>.</p>
    <?php else: ?>
      <div class="a-bar"><h2>Edit page: /<?= $e($page['slug']) ?></h2><a class="btn btn-sm" href="/admin?tab=pages">&larr; All pages</a><a class="btn btn-sm" href="/<?= $e($page['slug']) ?>" target="_blank" rel="noopener">View on site</a></div>
      <form method="post" class="box editor-form">
        <?= $hidden('csrf', $csrf) . $hidden('action', 'save-page') . $hidden('tab', 'pages') . $hidden('slug', $page['slug']) ?>
        <label>Page title<input name="title" value="<?= $e($page['title']) ?>" required maxlength="200"></label>
        <label>Short description <span class="muted small">(for Google)</span><input name="description" value="<?= $e($page['description']) ?>" maxlength="300"></label>
        <?php if ($page['slug'] === 'faq'): ?><p class="muted small">This is the text above the questions. Edit the questions under <a href="/admin?tab=faq">FAQ</a>.</p><?php endif; ?>
        <?php if ($page['slug'] === 'contact'): ?><p class="muted small">This is the text above the contact form. Messages people send show up under <a href="/admin?tab=messages">Messages</a>.</p><?php endif; ?>
        <?php $editorName = 'body'; $editorValue = $page['body']; require __DIR__ . '/admin-editor.php'; ?>
        <div class="a-bar"><button class="btn btn-primary">Save page</button></div>
      </form>
      <?php if ($page['custom']): ?>
        <form method="post" data-confirm="Put the original text back? Your changes to this page will be lost."><?= $hidden('csrf', $csrf) . $hidden('action', 'reset-page') . $hidden('tab', 'pages') . $hidden('slug', $page['slug']) ?><button class="btn btn-sm danger">Reset to original text</button></form>
      <?php endif; ?>
    <?php endif; ?>

  <?php elseif ($tab === 'faq'): ?>
    <h2>Add a question</h2>
    <form method="post" class="box">
      <?= $hidden('csrf', $csrf) . $hidden('action', 'add-faq') . $hidden('tab', 'faq') ?>
      <label>Question<input name="question" required maxlength="500"></label>
      <label>Answer<textarea name="answer" rows="3" required maxlength="5000"></textarea></label>
      <button class="btn btn-primary">Add question</button>
    </form>
    <h2>Questions on the <a href="/faq" target="_blank" rel="noopener">FAQ page</a> <span class="muted small">(lower order number shows first)</span></h2>
    <?php foreach ($faqs as $f): ?>
      <form method="post" class="box faq-edit">
        <?= $hidden('csrf', $csrf) . $hidden('action', 'save-faq') . $hidden('tab', 'faq') . $hidden('id', $f['id']) ?>
        <div class="faq-top"><label class="pos">Order<input name="position" type="number" value="<?= (int) $f['position'] ?>"></label><label class="grow">Question<input name="question" value="<?= $e($f['question']) ?>" required maxlength="500"></label></div>
        <label>Answer<textarea name="answer" rows="3" required maxlength="5000"><?= $e($f['answer']) ?></textarea></label>
        <button class="btn btn-sm btn-primary">Save</button>
        <button class="btn btn-sm danger" name="action" value="delete-faq" data-confirm="Delete this question?">Delete</button>
      </form>
    <?php endforeach; ?>
    <?php if (!$faqs): ?><p class="muted">No questions yet.</p><?php endif; ?>

  <?php elseif ($tab === 'messages'): ?>
    <div class="a-bar"><h2>Messages from the Contact page</h2>
      <?php if ($unread): ?><?= $form('read-messages', 'Mark all as read') ?><?php endif; ?></div>
    <?php foreach ($messages as $m): ?>
      <div class="box msg-card<?= $m['seen'] ? '' : ' unseen' ?>">
        <div class="a-bar"><strong><?= $e($m['name']) ?></strong> <a href="mailto:<?= $e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?: 'your message')) ?>"><?= $e($m['email']) ?></a>
          <span class="muted small" style="margin-left:auto"><?= $t($m['created_at']) ?></span></div>
        <?php if ($m['subject'] !== ''): ?><div><strong><?= $e($m['subject']) ?></strong></div><?php endif; ?>
        <div class="msg-body"><?= nl2br($e($m['body'])) ?></div>
        <div class="a-bar"><a class="btn btn-sm btn-primary" href="mailto:<?= $e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?: 'your message')) ?>">Reply by email</a>
          <form method="post" class="inline" data-confirm="Delete this message?"><?= $hidden('csrf', $csrf) . $hidden('action', 'delete-message') . $hidden('tab', 'messages') . $hidden('id', $m['id']) ?><button class="btn btn-sm danger">Delete</button></form></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$messages): ?><p class="muted">No messages yet. When someone uses the <a href="/contact" target="_blank" rel="noopener">Contact page</a>, it shows up here.</p><?php endif; ?>

  <?php elseif ($tab === 'settings'): ?>
    <h2>Site text and links</h2>
    <form method="post" class="box">
      <?= $hidden('csrf', $csrf) . $hidden('action', 'save-settings') . $hidden('tab', 'settings') ?>
      <?php foreach (Tms\Content::SETTINGS as $key => [$label]): ?>
        <?php if ($key === 'social_facebook'): ?><h3>Follow Us links <span class="muted small">(leave empty to hide an icon)</span></h3><?php endif; ?>
        <label><?= $e($label) ?><input name="<?= $e($key) ?>" value="<?= $e($settings[$key]) ?>" maxlength="1000"<?= str_starts_with($key, 'social_') ? ' type="url" placeholder="https://"' : '' ?>></label>
      <?php endforeach; ?>
      <button class="btn btn-primary">Save settings</button>
    </form>

  <?php elseif ($tab === 'domains'): ?>
    <h2>Add a mail domain</h2>
    <form method="post" class="box row-form">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="add-domain">
      <input name="domain" placeholder="newdomain.com" required autocomplete="off" autocapitalize="none">
      <button class="btn btn-primary">Add domain</button>
    </form>

    <h2>Your domains</h2>
    <div class="tbl-wrap"><table>
      <tr><th>Domain</th><th>Status</th><th class="num">Live addresses</th><th>Mail server (MX)</th><th></th></tr>
      <?php foreach ($domains as $d): [$mx, $dnsOk] = $d['mx']; ?>
        <tr>
          <td><strong><?= $e($d['name']) ?></strong><?php if ($d['name'] === $default): ?> <span class="pill on">default</span><?php endif; ?></td>
          <td><?= $d['active'] ? '<span class="pill on">on</span>' : '<span class="pill off">paused</span>' ?></td>
          <td class="num"><?= $n($d['live']) ?></td>
          <td class="small"><?php if (!$dnsOk): ?><span class="muted">could not check</span><?php elseif (!$mx): ?><span style="color:var(--danger)">No MX record: mail will not arrive</span><?php else: ?><?= $e(implode(', ', $mx)) ?><?php endif; ?></td>
          <td style="white-space:nowrap">
            <?php if ($d['name'] !== $default): ?><?= $form('default-domain', 'Make default', $d['name']) ?><?php endif; ?>
            <?= $form('toggle-domain', $d['active'] ? 'Pause' : 'Turn on', $d['name']) ?>
            <?php if (!$d['live']): ?><?= $form('remove-domain', 'Remove', $d['name'], 'btn btn-sm danger') ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <p class="muted small">Paused domains are hidden for new addresses, but addresses already made on them keep receiving mail.</p>

    <h2>Make a new domain receive mail</h2>
    <div class="box">
      <p><strong>Option A: free Cloudflare (best, no hosting limit)</strong></p>
      <ol class="small">
        <li>Add the domain to a free Cloudflare account and change its nameservers at your registrar.</li>
        <li>Cloudflare &rarr; Workers &amp; Pages &rarr; Create Worker, paste <code>worker/email-worker.js</code> from the GitHub repo.</li>
        <li>Worker &rarr; Settings &rarr; Variables: <code>SITE_URL</code> = <code><?= $e($siteUrl) ?></code> and secret <code>INGEST_SECRET</code> = <code><?= $e($secret) ?></code></li>
        <li>Domain &rarr; Email &rarr; Email Routing &rarr; enable it, then Routing rules &rarr; Catch-all &rarr; Send to a Worker &rarr; pick the worker.</li>
        <li>Add the domain here. The MX column turns into <code>route1.mx.cloudflare.net</code> when it is ready.</li>
      </ol>
      <p><strong>Option B: on this cPanel hosting</strong></p>
      <ol class="small">
        <li>cPanel &rarr; Domains &rarr; add the domain (any folder is fine).</li>
        <li>cPanel &rarr; Default Address &rarr; pick the domain &rarr; Advanced &rarr; Pipe to a Program: <code><?= $e($pipe) ?></code></li>
        <li>Add the domain here.</li>
      </ol>
    </div>

  <?php elseif ($tab === 'mail'): ?>
    <?php
    $pager = function (int $total, int $page, string $key) use ($per, $q, $e): string {
        $pages = max(1, (int) ceil($total / $per));
        if ($pages < 2) {
            return '';
        }
        $link = fn (int $p, string $label) => '<a class="btn btn-sm" href="/admin?' . $e(http_build_query(array_filter(['tab' => 'mail', 'q' => $q, 'mp' => $_GET['mp'] ?? null, 'bp' => $_GET['bp'] ?? null]) + [$key => $p])) . '">' . $label . '</a>';
        return '<div class="a-bar">' . ($page > 1 ? $link($page - 1, '&larr; Newer') : '') . '<span class="muted small">Page ' . $page . ' of ' . $pages . '</span>' . ($page < $pages ? $link($page + 1, 'Older &rarr;') : '') . '</div>';
    };
    ?>
    <form method="get" class="box row-form" style="margin-top:14px">
      <input type="hidden" name="tab" value="mail">
      <input name="q" value="<?= $e($q) ?>" placeholder="Search address, sender or subject" autocomplete="off" autocapitalize="none">
      <button class="btn btn-primary">Search</button>
      <?php if ($q !== ''): ?><a class="btn" href="/admin?tab=mail">Clear</a><?php endif; ?>
    </form>
    <p class="muted small">Every email and address is kept here for you, even after the visitor deletes it or it expires. Visitors only see email for 3 hours.</p>

    <h2>All emails received <span class="muted small">(<?= $n($emailTotal) ?>)</span></h2>
    <div class="tbl-wrap"><table>
      <tr><th>Time</th><th>To</th><th>From</th><th>Subject</th><th>Code</th><th>Status</th><th class="num">Delay</th><th>Route and time at each server</th></tr>
      <?php foreach ($emails as $m): ?>
        <tr>
          <td class="small" style="white-space:nowrap"><?= $t($m['received_at']) ?></td>
          <td class="small"><a href="/admin?tab=mail&amp;q=<?= $e(rawurlencode((string) $m['address'])) ?>"><?= $e($m['address']) ?></a></td>
          <td class="small"><?= $e($m['from_addr']) ?></td>
          <td class="small" style="min-width:200px">
            <details><summary><?= $e(mb_strimwidth((string) $m['subject'], 0, 70, '…') ?: '(no subject)') ?></summary>
              <div class="mail-peek"><?= $e(mb_strimwidth((string) $m['text_body'], 0, 4000, '…')) ?></div>
              <?php if ($m['attachments']): ?><div class="muted small"><?= (int) $m['attachments'] ?> attachment(s), <?= $size((int) $m['size']) ?></div><?php endif; ?>
            </details>
          </td>
          <td class="small"><?= $e((string) $m['otp']) ?></td>
          <td class="small"><?= $m['in_inbox'] ? '<span class="pill on">in inbox</span>' : '<span class="pill">removed</span>' ?></td>
          <td class="num small"><?= $m['sent_at'] ? max(0, (int) $m['received_at'] - (int) $m['sent_at']) . 's' : '-' ?></td>
          <td class="small" style="min-width:260px">
            <?php if ($m['via']): ?><span class="pill <?= $m['via'] === 'cloudflare' ? 'on' : '' ?>"><?= $m['via'] === 'cloudflare' ? 'Cloudflare' : 'Hosting mail server' ?></span><br><?php endif; ?>
            <?php
            $prev = $m['sent_at'] ? (int) $m['sent_at'] : null;
            $steps = $prev ? ['sent'] : [];
            foreach ((array) json_decode((string) $m['trace'], true) as [$by, $at]) {
                $steps[] = $e($by) . ($prev !== null ? ' <strong>+' . ((int) $at - $prev) . 's</strong>' : '');
                $prev = (int) $at;
            }
            if ($prev !== null) {
                $steps[] = 'saved <strong>+' . ((int) $m['received_at'] - $prev) . 's</strong>';
            }
            echo implode(' &rarr; ', $steps);
            ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$emails): ?><tr><td colspan="8" class="muted">No emails<?= $q !== '' ? ' match your search' : ' yet' ?>.</td></tr><?php endif; ?>
    </table></div>
    <?= $pager($emailTotal, $mp, 'mp') ?>

    <h2>All addresses created <span class="muted small">(<?= $n($boxTotal) ?>, the same address shows again each time it is made)</span></h2>
    <div class="tbl-wrap"><table>
      <tr><th>Address</th><th>Created</th><th>Status</th><th class="num">Emails</th></tr>
      <?php foreach ($boxes as $b): ?>
        <tr>
          <td class="small"><a href="/admin?tab=mail&amp;q=<?= $e(rawurlencode((string) $b['address'])) ?>"><?= $e($b['address']) ?></a></td>
          <td class="small" style="white-space:nowrap"><?= !empty($b['backfilled']) ? '<span class="muted">before history</span>' : $t($b['created_at']) ?></td>
          <td class="small" style="white-space:nowrap">
            <?php if ($b['ended_at'] === null && $b['expires_at'] !== null && (int) $b['expires_at'] > time()): ?>
              <span class="pill on">live</span> until <?= $t($b['expires_at']) ?>
            <?php elseif ($b['ended_at'] !== null): ?>
              <span class="pill off"><?= $e($b['end_reason'] === 'deleted by owner' ? 'deleted' : ($b['end_reason'] === 'expired' ? 'expired' : (string) $b['end_reason'])) ?></span> <?= $t($b['ended_at']) ?>
              <?php if ((int) $b['ended_at'] + Tms\Mailbox::REUSE_AFTER > time()): ?><div class="muted small">name free again <?= $t((int) $b['ended_at'] + Tms\Mailbox::REUSE_AFTER) ?></div><?php endif; ?>
            <?php else: ?>
              <span class="pill off">expired</span>
            <?php endif; ?>
          </td>
          <td class="num small"><?= $n($b['emails']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$boxes): ?><tr><td colspan="4" class="muted">No addresses<?= $q !== '' ? ' match your search' : ' yet' ?>.</td></tr><?php endif; ?>
    </table></div>
    <?= $pager($boxTotal, $bp, 'bp') ?>

  <?php else: ?>
    <?php $titles = ['error' => 'Errors', 'ingest' => 'Mail problems (dropped / too big)', 'cron' => 'Cleanup runs', 'admin' => 'Admin activity']; ?>
    <?php foreach ($logs as $channel => $lines): ?>
      <h2><?= $e($titles[$channel]) ?> <span class="muted small">(newest first, times in UTC)</span></h2>
      <div class="log"><?= $lines ? $e(implode("\n", $lines)) : '<span class="muted">Nothing logged.</span>' ?></div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
