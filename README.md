# Temp Mail Site

Free disposable email: random or custom address, live inbox, one-tap verification codes,
sandboxed HTML view, attachments, recovery code and QR device linking. Runs on plain
shared hosting (cPanel, PHP 8.1+) with SQLite, so there is no database to set up.

## Layout

| Path | What it is |
| --- | --- |
| `public/` | Web root (point the domain's document root here) |
| `app/src/` | PHP classes (`Tms\...`) |
| `app/views/`, `app/content/blog/` | Pages and blog posts |
| `bin/pipe.php` | cPanel "Default Address → Pipe to a Program" target |
| `bin/cron.php` | Cleanup, run every 10 minutes |
| `worker/email-worker.js` | Cloudflare Email Worker for extra domains |
| `storage/` | Created on first run: `config.php`, SQLite DB, attachments, logs (not in git) |

## Deploy on cPanel

1. **Git Version Control → Create**: clone `https://github.com/udoybd/temp-mail-site` into `temp-mail-site`.
2. **Domains → Manage** the site domain and set its document root to `temp-mail-site/public`.
3. Open the site once; it writes `storage/config.php` with a random secret.
4. **Default Address** for the domain → *Pipe to a Program* → `temp-mail-site/bin/pipe.php`.
5. **Cron Jobs** → every 10 minutes: `/usr/local/bin/php /home/USER/temp-mail-site/bin/cron.php >/dev/null 2>&1`
6. Updates: Git Version Control → Manage → *Update from Remote*.

## Settings

Edit `storage/config.php` on the server: `domains`, `site_url`, limits, `adsense_client`, `analytics_id`.

## Extra mail domains (Cloudflare, free)

Add the domain to Cloudflare, enable Email Routing, set the catch-all to *Send to a Worker*
using `worker/email-worker.js`, then add the domain to `domains` in `storage/config.php`.

## Local development

```sh
composer install && bin/trim-vendor.sh
TMS_STORAGE=/tmp/tms php -S 127.0.0.1:8080 -t public tests/dev-router.php
```
`vendor/` is committed because shared hosting usually has no Composer.

## Admin panel

Open `/admin`. The first time, it asks for the setup code from
`storage/admin-setup-code.txt` (open it in cPanel File Manager) and a new password.
Forgot the password? Delete `storage/admin.php` and open `/admin` again.

The panel shows counts (addresses and emails per day), live totals, delivery speed,
whether the cleanup cron runs, the latest emails and addresses, and the logs.
Mail domains are added, paused, made default or removed under **Domains**.
