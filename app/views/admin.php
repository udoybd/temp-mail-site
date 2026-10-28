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
$labels = ['dashboard' => 'Dashboard', 'domains' => 'Domains', 'mail' => 'Mail', 'logs' => 'Logs'];
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
<link rel="stylesheet" href="/assets/app.css?v=5">
<style>
.admin { max-width: 1080px; margin: 0 auto; padding: 16px; }
.admin h1 { font-size: 22px; margin: 0; }
.admin h2 { font-size: 18px; margin: 28px 0 10px; }
.a-top { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.a-tabs { display: flex; gap: 6px; margin: 14px 0 6px; overflow-x: auto; }
.a-tabs a { padding: 8px 14px; border-radius: 10px; border: 1px solid var(--line); color: var(--muted); text-decoration: none; white-space: nowrap; }
.a-tabs a.on { background: var(--accent); border-color: var(--accent); color: #fff; }
.tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.tile { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 14px; }
.tile .k { color: var(--muted); font-size: 13px; }
.tile .v { font-size: 26px; font-weight: 700; line-height: 1.2; }
.tile .s { color: var(--muted); font-size: 12px; }
.tbl-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: var(--radius); }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
th { color: var(--muted); font-weight: 600; background: var(--bg2); white-space: nowrap; }
tr:last-child td { border-bottom: 0; }
td.num, th.num { text-align: right; }
.bars { display: flex; align-items: flex-end; gap: 4px; height: 120px; padding: 10px; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); }
.bars .col { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; gap: 2px; min-width: 0; }
.bars .b { width: 100%; border-radius: 4px 4px 0 0; min-height: 2px; }
.bars .b.mb { background: var(--accent); }
.bars .b.em { background: var(--accent2); }
.bars .d { font-size: 10px; color: var(--muted); }
.legend { display: flex; gap: 14px; font-size: 13px; color: var(--muted); margin-top: 6px; }
.legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; }
.inline { display: inline; }
.msg { padding: 10px 14px; border-radius: 10px; margin: 10px 0; }
.msg.ok { background: rgba(34, 211, 166, .12); border: 1px solid var(--accent2); }
.msg.err { background: rgba(255, 92, 122, .12); border: 1px solid var(--danger); }
.pill { display: inline-block; padding: 1px 8px; border-radius: 99px; font-size: 12px; border: 1px solid var(--line); }
.pill.on { color: var(--accent2); border-color: var(--accent2); }
.pill.off { color: var(--danger); border-color: var(--danger); }
.box { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 16px; }
.box input { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg2); color: var(--text); font: inherit; margin: 4px 0 10px; }
.row-form { display: flex; gap: 8px; flex-wrap: wrap; }
.row-form input { flex: 1; min-width: 180px; margin: 0; }
.log { background: var(--bg2); border: 1px solid var(--line); border-radius: 10px; padding: 10px; font: 12px/1.5 ui-monospace, monospace; white-space: pre-wrap; word-break: break-all; max-height: 360px; overflow: auto; }
code { background: var(--bg2); padding: 1px 6px; border-radius: 6px; word-break: break-all; }
ol li { margin-bottom: 6px; }
.auth { max-width: 420px; margin: 8vh auto; }
</style>
</head>
<body>
<div class="admin">
<?php if ($tab === 'setup' || $tab === 'login'): ?>
  <div class="auth">
    <a class="logo" href="/"><span class="logo-mark" aria-hidden="true">@</span><?= $e($site) ?> admin</a>
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
    <a class="logo" href="/"><span class="logo-mark" aria-hidden="true">@</span><?= $e($site) ?></a>
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
    <h2>Latest emails received</h2>
    <div class="tbl-wrap"><table>
      <tr><th>Time</th><th>To</th><th>From</th><th>Subject</th><th>Code</th><th class="num">Delay</th></tr>
      <?php foreach ($emails as $m): ?>
        <tr>
          <td class="small" style="white-space:nowrap"><?= $t($m['received_at']) ?></td>
          <td class="small"><?= $e($m['address']) ?></td>
          <td class="small"><?= $e($m['from_addr']) ?></td>
          <td class="small"><?= $e(mb_strimwidth((string) $m['subject'], 0, 70, '…')) ?></td>
          <td class="small"><?= $e((string) $m['otp']) ?></td>
          <td class="num small"><?= $m['sent_at'] ? max(0, (int) $m['received_at'] - (int) $m['sent_at']) . 's' : '-' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$emails): ?><tr><td colspan="6" class="muted">No emails stored right now (each is kept 3 hours).</td></tr><?php endif; ?>
    </table></div>

    <h2>Latest addresses created</h2>
    <div class="tbl-wrap"><table>
      <tr><th>Address</th><th>Created</th><th>Expires</th><th class="num">Emails</th></tr>
      <?php foreach ($boxes as $b): ?>
        <tr>
          <td class="small"><?= $e($b['address']) ?></td>
          <td class="small" style="white-space:nowrap"><?= $t($b['created_at']) ?></td>
          <td class="small" style="white-space:nowrap"><?= $b['expires_at'] < time() ? '<span class="pill off">expired</span>' : $t($b['expires_at']) ?></td>
          <td class="num small"><?= $n($b['emails']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$boxes): ?><tr><td colspan="4" class="muted">No addresses yet.</td></tr><?php endif; ?>
    </table></div>

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
