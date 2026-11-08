---
name: bengal-email-site
description: Build, deploy, fix or maintain Jamal's Bengal.email temp mail site (https://bengal.email) on folafal.com DirectAdmin hosting + free Cloudflare Email Routing, repo udoybd/temp-mail-site.
---

# Bengal.email (Temp Mail Site)

Free disposable email site, live at **https://bengal.email**. Brand name everywhere is **Bengal.email** (not "Bengali", not "Bengal Email", not "Temp Mail Site"). Every page title starts with `Bengal.email` (home: `Bengal.email: Free Temporary Email Address Generator`, others: `Bengal.email | FAQ`, admin: `Bengal.email | Admin`). Logo = blue envelope icon + "Bengal" + blue ".email" (owner picked design no. 1 on 2026-11-04; do not change it).

The old test site on **jamaluddin.info is obsolete**: its files, Git link, cron and mail pipe were removed on 2026-11-04. The domain is kept but empty (returns 403). Never deploy there and never treat jamaluddin.info as the site.

## Working rules (owner's limit is precious)
- **Do not re-read this skill's long sections, old chats or big files again and again.** Read only the few files you must change; if you really must read something big, tell the owner why in one line.
- Everything not part of today's request must stay exactly as it is.
- Reply short, in Bangla. Test locally first (`php -S`, Playwright is installed), then deploy.
- **Live uploads need the owner's explicit "লাইভে দিন" each time**: the system blocks production deploys until the owner says so in chat. Prepare, push to the PR branch, ask once, then upload.

## Owner and communication
- Owner: Jamal Uddin, GitHub `udoybd`. Always reply in **Bangla**, simple language, short. Works mostly from the phone.
- Give tap-by-tap steps for anything they must do themselves (DirectAdmin, Cloudflare, Namecheap, GitHub).
- Budget: **free only**. No paid services or plans.
- Never type passwords or secrets yourself. If a login has expired, ask the owner to log in, then continue.
- Ask before anything that cannot be undone (deleting files, domains, DNS, database rows). Make a backup first.
- Owner likes fast, finished work: once a task is clear, do it end to end and report the result.

## Hard warnings
- **Do NOT touch `bdupdate.online`** or anything else on the old cPanel (jamaluddin.info:2083, user `bdupdat1`). It is a separate live WordPress site.
- Old jamaluddin.info temp-mail files are gone. A backup zip `tms-migrate.zip` is in the owner's PC Downloads folder.
- **No Premium page.** Everything is free. `/premium` and `/inbox` redirect to `/#inbox`. Do not add paid plans unless the owner asks.
- Do not replace `public/sw.js` with an ad network's service worker (Monetag sw.js was tried and rolled back on owner's request). Ad/verification code goes in **Admin → Code** instead. Keep the Monetag verification meta tag that is in `app/views/layout.php`.
- **GitHub and live server were synced on 2026-11-07** (PR #5 branch `claude/project-thread-ywd0s1`, includes Code tab and domain Delete). Still compare live vs repo (file hashes) before any big deploy; never upload a full repo copy over `storage/` or `public_html/uploads/`.

## Architecture
```
Sender -> MX (Cloudflare Email Routing, bengal.email zone)
       -> catch-all rule "Send to a Worker" -> Worker `temp-mail-site` (worker/email-worker.js)
       -> POST https://bengal.email/api/incoming  (headers X-Ingest-Secret, X-Rcpt-To; body = raw RFC822)
       -> PHP parses MIME, stores in SQLite if a live mailbox matches
Browser -> polls /api every poll_interval seconds (default 2s)
```
- **Hosting:** folafal.com shared hosting, **DirectAdmin** panel at `https://da2.cpanelaccess.com:2222/evo/` (allows ~5 domains). bengal.email is added there; document root must point to the repo's `public/` folder. PHP 8.1+, SQLite (no MySQL needed).
- **DNS/CDN:** domain registered at **Namecheap**, nameservers moved to **Cloudflare** (free). Cloudflare proxies the site with SSL.
- **Mail:** Cloudflare Email Routing catch-all on bengal.email -> Worker `temp-mail-site`. Worker vars: `SITE_URL=https://bengal.email`, `INGEST_SECRET` = `ingest_secret` from server `storage/config.php`. Worker does no parsing (free plan 10 ms CPU). On a 5xx from the site it rejects temporarily so the sender retries.
- **Cron:** every 10 minutes runs `php .../bin/cron.php` (cleanup). Set up in DirectAdmin Cron Jobs. Admin dashboard shows if cron is running (`settings.cron_last_run`).
- Old cPanel pipe path (`bin/pipe.php`, Default Address -> Pipe to program) still exists in code but is **not used** on bengal.email.
- Free limits: Workers Free = 100,000 requests/day (one per incoming email). Max message size in code: 10 MB (`max_message_bytes`).

## Repo layout (udoybd/temp-mail-site, branch `main`)
| Path | What |
| --- | --- |
| `public/` | Web root: `index.php` router, `.htaccess`, `assets/` (app.css, app.js, admin.css, admin.js, icons, og.png), `sw.js` (PWA), `manifest.webmanifest`, `robots.txt`. `public/uploads/` = admin image uploads (not in git) |
| `app/bootstrap.php` | Autoload, `TMS_ROOT`, `TMS_STORAGE` (env or `storage/`) |
| `app/src/` | PHP classes, namespace `Tms\` (see below) |
| `app/views/` | Templates: `layout.php`, `home.php`, `page.php`, `blog-index.php`, `blog-post.php`, `admin.php`, `admin-editor.php`, `pages/delete-account.php`, `pages/not-found.php` |
| `app/content/` | Original text for pages, FAQ and first blog posts. Copied into the DB once; after that edited in /admin |
| `bin/cron.php` | Cleanup job (every 10 min) |
| `bin/pipe.php` | Old cPanel pipe target (unused now) |
| `bin/trim-vendor.sh` | Shrinks `vendor/` after composer install |
| `worker/email-worker.js` | Cloudflare Email Worker source |
| `vendor/` | Committed on purpose (shared hosting has no Composer). Main lib: zbateson/mail-mime-parser |
| `storage/` | On server only, not in git: `config.php`, SQLite DB, `attachments/`, `logs/`, `admin.php` (admin password hash), `admin-setup-code.txt` |
| `tests/dev-router.php` | Local dev router |

PHP classes in `app/src/`: `Account` (device cookie `tms_dev`, recovery code, QR link codes, delete), `Admin` (whole admin panel), `Api` (JSON API), `Cleanup`, `Config` (defaults + `storage/config.php`), `Content` (pages, blog, FAQ, settings in DB), `Db` (SQLite + migrations), `Http`, `Log`, `Mailbox`, `Message` (MIME ingest, attachments), `Otp` (code + verify-link detection), `RateLimit`, `Stats` (daily counts), `UserError`, `View` (public routes).

## Mail rules (decided by owner, keep them)
- Every address lives exactly **24 hours** from creation (`Mailbox::LIFETIME = 86400`, fixed in code; config ttl is ignored). Same for guest and linked accounts.
- Each email is deleted `email_ttl` after it arrives (default 3 hours in `Config::defaults()`; check server `storage/config.php` for an override).
- **Delete** an address -> it is freed **immediately**; anyone can take that name again.
- **Change / New** (switching away) -> the old address stays **reserved for its owner** until its 24 h expiry; others get "That address is in use. It becomes free in about N hours".
- Typing the name of an address you already own (still live) just **reopens** it.
- `user+tag@bengal.email` lands in `user@bengal.email`.
- Reserved names nobody can take: admin, administrator, support, postmaster, abuse, info, root, webmaster, hostmaster, noreply, no-reply, mail, security, contact, help.
- Custom names: 3-30 chars, a-z 0-9 . _ - (no `..`). Random names = word + animal + number (e.g. `cosmiccomet6823`).
- Limits: 30 new addresses per IP per day, 10 live addresses per account, restore/delete-by-code 20 tries/hour/IP, contact form 5/hour/IP.
- **Admin Mail keeps every record forever** (`mail_log` + `mailbox_log`), even after the user deletes or cleanup runs. Message bodies themselves are deleted by the rules above.
- These rules are also written in the public FAQ. If a rule changes, update code, FAQ text (Admin → FAQ) and this skill.

## Public features
- Home `/`: random address on load, Copy, Change (custom name + domain picker), New, Delete, expiry countdown, live inbox (polling), sender favicon, unread count.
- Email view: OTP/verification code detection with one-tap copy, verify-link detection, sandboxed HTML view (`/m/{id}/html`, CSP sandbox, no scripts), plain text, attachments download (`/attachment/{id}`, inline images via cid).
- Account pop-up: list of your addresses, recovery code (restore on another device), QR / link to sync a device (`/link/{code}`), delete account (`/delete-account`, also by recovery code).
- Design: light gradient, indigo `#4f2ee8` buttons, header Home / Features / FAQ / Blog / Contact, language picker (Google Translate), dark mode toggle, PWA (installable, `sw.js`).
- Pages (editable in admin): `/features`, `/faq`, `/contact` (form), `/how-it-works`, `/about`, `/support`, `/privacy`, `/terms`. Plus `/blog`, `/blog/{slug}`, `/sitemap.xml`, `/robots.txt`, 404 page.
- Follow Us icons (Facebook, X, Telegram, YouTube) show only when the link is set in Admin → Settings.
- Ads: AdSense via `adsense_client`/`adsense_slot` in config (hidden while empty); Monetag/other code via Admin → Code. Analytics via `analytics_id` or Admin → Code.

## API endpoints (`app/src/Api.php`)
All non-GET calls except `/api/incoming` need header `X-TMS: 1` (CSRF guard). JSON in/out.
| Method | Path | Does |
| --- | --- | --- |
| GET | `/api/state` | domains, allow_custom, poll_seconds, account info, mailboxes |
| POST | `/api/mailboxes` | create address `{name?, domain?}`; reopens own live address if name matches |
| GET | `/api/mailboxes/{id}` | mailbox + message list |
| DELETE | `/api/mailboxes/{id}` | delete address (frees name now) |
| GET | `/api/messages/{id}` | full message (marks seen), attachments |
| DELETE | `/api/messages/{id}` | delete one email |
| POST | `/api/account/recovery-code` | new recovery code |
| POST | `/api/account/restore` | `{code}` switch this device to that account |
| POST | `/api/account/link-code` | QR/link URL to sync another device |
| POST | `/api/account/delete` | delete current account, or `{code}` to delete by recovery code |
| POST | `/api/incoming` | Worker webhook: raw email, `X-Ingest-Secret`, `X-Rcpt-To` |
| POST | `/api/ping` | visitor heartbeat for Online Now and time on site |
| POST | `/api/contact` | contact form `{name,email,subject,message}`, honeypot `website` |
Other routes: `/m/{id}/html`, `/attachment/{id}`, `/link/{code}`, `/sitemap.xml`.

## Admin panel: https://bengal.email/admin
Login: password set by the owner. First-ever setup asks for the code in `storage/admin-setup-code.txt`. **Forgot password:** delete `storage/admin.php` on the server (DirectAdmin File Manager) and open /admin again. Admin cookie is path `/admin`, httponly, SameSite Strict; all forms carry a CSRF token. Claude does not have the admin password, so it cannot test admin saves; ask the owner to check.

Tabs (`Admin::TABS`):
1. **Dashboard**: daily counts (addresses, emails), live totals, delivery speed, cron status, latest emails and addresses, change password, logout.
2. **Blog**: new/edit/delete post; Status = **Publish Now / Schedule / Draft**. Schedule asks date-time, or tick **Auto** = 09:00 the day after the last waiting post (Admin::nextSlot). Editing a live post keeps its date. Cover image upload + **Cover image Alt text** (`posts.cover_alt`, used as img alt in blog list and post). Editor: **Visual** (contenteditable, like WordPress classic; text selectable/copyable) / **Code (HTML)** / **Split**, always in sync (public/assets/admin.js). Bulk "Schedule all drafts" kept.
3. **Pages**: edit Features, FAQ intro, Contact intro, How it works, About, Support, Privacy, Terms; "Reset to original text".
4. **FAQ**: add, edit, reorder, delete questions on `/faq`.
5. **Messages**: contact form messages; mark read, delete.
6. **Settings**: home badge line, home title, ticks, footer text, Follow Us links (must start with http/https).
7. **Code**: Header code (before `</head>`) and Footer code (before `</body>`) on every page, max 20,000 chars, saved as-is. Use for Monetag, AdSense, Analytics, verification tags.
8. **Domains**: add mail domain, pause/resume, make default, remove/**Delete** (Delete button with confirm works even with live addresses). Domains live in DB table `domains`, seeded from config `domains`. A new mail domain also needs Cloudflare: add zone, Email Routing on, catch-all -> Worker `temp-mail-site`.
9. **Mail**: full history of every email and address (kept forever), with route (Cloudflare/Hosting) and per-hop delay, plus addresses deleted earlier.
10. **Visitors** (before Logs; class `Visitors`, table `visits`, migrations v12/v13): one row per visit (ends after 30 min idle) from public page views (bots skipped; cookie `tms_vid`; heartbeat `visit.js` -> `POST /api/ping` every 25 s). Columns: IP, Browser, Device (PC/Mobile/Tablet), Country (Cloudflare `CF-IPCountry`), Time, Stayed, Mails made (bumped in Mailbox::create), Came from (Direct or referrer link), First page (landing path). Filters: time (24h/7d/30d), device, browser, country, came from, first page (Home/Blog/Other), made-mail, text search; newest first; 50 per page. Top badge "Online Now - N" (seen in last 70 s, refreshes every 15 s). Table fits the screen; column widths are draggable and saved in `localStorage` key `tms_vis_cols_v1`; long text is cut with "…". Rows are deleted after 30 days by Cleanup (cron); privacy page says so. IP is shown as received: IPv6 is normal on mobile networks; the real IPv4 cannot be known (Cloudflare "Pseudo IPv4" is fake, not used).
11. **Logs**: app logs (cron runs, errors, ingest).

## Live layout and how to deploy without SSH
Live folder `/domains/bengal.email/` (DirectAdmin): `app/ bin/ vendor/ storage/ public_html/` — **`public/` in git = `public_html/` on the server** (`public_html/index.php` requires `../app/bootstrap.php`). Uploaded images live in `public_html/uploads/` (Content::saveImage uses DOCUMENT_ROOT, fixed 2026-11-07; before that they went to a dead `public/uploads`).
Deploy used on 2026-11-07, from the owner's logged-in Chrome (claude-in-chrome) on the DirectAdmin tab `da2.cpanelaccess.com:2222`:
1. Push changes to the PR branch (repo is public). 2. In the DA tab run JS: `fetch(raw.githubusercontent.com/udoybd/temp-mail-site/<branch>/<file>)`, then POST multipart to `/CMD_API_FILE_MANAGER` with `action=upload, path=<server dir>, overwrite=yes, file1=<blob>`. 3. Read the file back from `/CMD_FILE_MANAGER/<path>` and compare. Delete: POST `action=multiple&button=delete&path=..&select0=..`. List: `/CMD_API_FILE_MANAGER?action=json_all&path=..`. 4. Bump `?v=` in views for changed CSS/JS (admin.css/js are at v4/v3, visit.js v1). 5. Check pages return 200 from a normal browser tab (the cloud container cannot reach bengal.email). DB migrations run on the next request.

## Server settings (`storage/config.php` on server)
Generated on first run. Keys: `site_name`, `site_url` (https://bengal.email), `domains`, `reserved`, `support_email`, `allow_custom_names`, `email_ttl`, `idle_account_ttl` (30 days), `mailboxes_per_ip_per_day`, `max_mailboxes_per_account`, `max_message_bytes`, `poll_interval`, `ingest_secret` (must equal Worker `INGEST_SECRET`), `app_key`, `adsense_client`, `adsense_slot`, `analytics_id`, `timezone` (Asia/Dhaka). Never print `ingest_secret` or `app_key` in chat or commit them.

## Deploy / update steps
1. Make the change on a branch in `udoybd/temp-mail-site`, open a PR, owner merges to `main` (or commit straight to main only if the owner asks).
2. Put the changed files on the server (see "Live layout and how to deploy" above): DirectAdmin → File Manager → bengal.email site folder, upload/replace only the changed files (keep `storage/` and `public/uploads/` untouched). If a Remote Control session on the owner's PC is available, Claude can do this through the owner's logged-in Chrome.
3. After CSS/JS/sw.js changes: Cloudflare → bengal.email → Caching → Purge (the file URLs, or everything).
4. Check: `/`, `/faq`, `/blog`, `/admin` return 200; create an address and ask the owner to send a test mail from Gmail; check Admin → Mail shows it.
5. DB schema changes go in migrations inside `Db.php` (runs automatically on next request). Back up `storage/` (DB) before a migration.
6. Keep GitHub and the live server identical; if you edit live, commit the same change to GitHub in the same task.

Local dev:
```sh
composer install && bin/trim-vendor.sh
TMS_STORAGE=/tmp/tms php -S 127.0.0.1:8080 -t public tests/dev-router.php
```

## Troubleshooting
- **Mail not arriving:** Cloudflare → bengal.email → Email → Email Routing: catch-all enabled and action = Worker `temp-mail-site`; MX records are Cloudflare's; Worker vars `SITE_URL`, `INGEST_SECRET` correct (403 from /api/incoming = secret mismatch); Admin → Logs; mailbox may be expired (24 h) or deleted.
- **Slow mail:** Admin → Mail shows per-hop delay; Cloudflare path is normally a few seconds.
- **Old mail/addresses not cleaned:** cron not running; Dashboard shows last cron run; fix DirectAdmin Cron Jobs.
- **404 on whole site after upload:** check document root points to `public/` and folder permissions (old cPanel needed 0711).
- **Admin locked out:** delete `storage/admin.php`, reopen /admin, use the code in `storage/admin-setup-code.txt`.

## History (short)
- 2026-10-28: built on jamaluddin.info (cPanel), moved mail to Cloudflare Email Routing + Worker for speed.
- 2026-11-07 (PR #5): Visitors tab, blog Publish Now/Schedule/Auto/Draft, cover image fix + alt text, Visual/Code/Split editor, resizable Visitors columns, live-only files synced to GitHub.
- 2026-11-04: redesign (indigo), editable content in admin (PR #1), 24 h rules (PR #2, #3), moved to bengal.email on folafal DirectAdmin, renamed Bengal.email, Monetag verified via meta tag, Code tab and domain Delete added live, jamaluddin.info emptied.
- Original goal: same features as ilovetempmail.com, but never copy its brand, logo, illustrations or text.

## Reference
- Full copy of this skill in the repo: `docs/skill/SKILL.md`.
- Original research and plan doc (ilovetempmail.com study): https://claude.ai/artifact/5rc254jfKB6A3LnV445ynU
