# Installation & deployment guide

One installation serves one business. Two supported layouts:

- **A. VPS / dedicated server** (recommended): Nginx or Apache with the
  document root on `public/`, a queue worker and Reverb under a supervisor.
- **B. Shared hosting** (cPanel/DirectAdmin): document root is the project
  root; the root `index.php` + `.htaccess` hand requests to `public/`.
  Real-time updates and background printing need a long-running process,
  which many shared hosts do not allow — see "Shared hosting limits".

## 1. System requirements

| | Minimum |
|---|---|
| PHP | 8.2, 8.3, 8.4 (8.5 per `composer.json`) |
| PHP extensions | `bcmath`, `ctype`, `curl`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml` (+ `intl` recommended) |
| Database | MySQL 8+ or MariaDB 10.6+ (`utf8mb4`) |
| Web server | Nginx or Apache 2.4 with `mod_rewrite` |
| Build | Composer 2, Node.js 18+ (only where assets are built) |
| Backup | `mysqldump`/`mysql` client binaries, `tar` |
| Printing | ESC/POS network printers reachable from the server (TCP 9100) |

The installer's first page checks PHP version, extensions and writable
directories.

## 2. Build a release

Assets must be built **after** Reverb credentials exist, because
`VITE_REVERB_*` values are compiled into the JavaScript:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan app:ensure-reverb-credentials
# set REVERB_HOST / REVERB_PORT / REVERB_SCHEME to the PUBLIC address now
npm ci && npm run build
```

`deploy/build-release.sh` does this and produces an archive without
`node_modules`, `.env`, logs or uploads (see section 10).

If assets were built without a Reverb key the app still works; the browser
console warns that real-time updates are disabled. Rebuild to enable them.

## 3. Layout A — VPS

```bash
# code at /var/www/cafe
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache .env
chmod 640 .env
```

- Nginx: `deploy/nginx/cafe.conf` (root **must** be `/var/www/cafe/public`;
  includes the Reverb WebSocket proxy on `/app/`).
- Apache: `DocumentRoot /var/www/cafe/public`, `AllowOverride All`.

Then open the site — a fresh install redirects to `/install`.

## 4. Layout B — shared hosting

1. Upload the built release into the folder your domain points to (e.g.
   `~/hani.example.site`). Keep the root `index.php` and `.htaccess` that
   ship with the release.
2. Do **not** edit the root `index.php`. In particular never add
   `declare(strict_types=1);` below any other line — PHP requires `declare`
   to be the very first statement, otherwise every request fails with
   *"strict_types declaration must be the very first statement in the
   script"*. The shipped file needs no `declare` at all.
3. Create the symlink: `php artisan storage:link` (or ask the host to create
   `public/storage → ../storage/app/public`).
4. Make `storage/`, `bootstrap/cache/` and `.env` writable by PHP.
5. Visit the domain → installer.

The root `.htaccess` refuses `.env`, dotfiles, `composer.*`, logs/SQL/backup
files and every application directory (`app/`, `vendor/`, `config/`, …) and
serves everything else from `public/`. Verified on Apache 2.4: `/.env`,
`/composer.json`, `/vendor/...`, `/app/...`, `/storage/logs/...` all return
403, while `/storage/<image>` (uploaded images) and `/build/...` are served.

If `mod_rewrite` is disabled the site will not work in this layout — ask the
host to enable it, or point the domain at `public/`.

### Shared hosting limits
- **No queue worker:** set `QUEUE_CONNECTION=sync`. Notifications and docket
  printing then run inside the request; an offline printer marks the print
  job failed (reprint from the order) instead of failing the request.
- **No Reverb:** leave `BROADCAST_CONNECTION=log`. The KDS refreshes itself
  every 20 s; order tracking and notification badges update on reload.

## 5. The web installer

`/install` → requirements → database (tests the connection, writes `.env`) →
migrations + roles → owner account → business details (name, currency,
locale, timezone) → complete. Completing it writes
`storage/app/private/installed.lock`, sets `APP_ENV=production`,
`APP_DEBUG=false`.

The installer stays closed afterwards even if the lock file is lost (for
example a redeploy that did not keep `storage/`): a database that already
has users counts as installed, and the lock is recreated.

## 6. Production `.env` checklist

| Key | Value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` (installer sets them) |
| `APP_URL` | the real `https://` URL — session cookies then become Secure automatically |
| `SESSION_DRIVER`, `CACHE_STORE` | `file` works everywhere; `database` after install is fine |
| `QUEUE_CONNECTION` | `database` with a worker (VPS) or `sync` (shared hosting) |
| `BROADCAST_CONNECTION` | `reverb` with the Reverb process, else `log` |
| `LOG_LEVEL` | `warning` |
| Gateways | prefer entering credentials in **Settings → Business**; they are stored encrypted with `APP_KEY` |

Keep a copy of `APP_KEY` outside the server. Without it the encrypted
gateway credentials cannot be read after a restore.

## 7. Background processes (VPS)

Run under systemd (`deploy/systemd/cafe-queue.service`,
`deploy/systemd/cafe-reverb.service`) or Supervisor (`deploy/supervisor/cafe.conf`);
edit user/paths first.

```bash
cp deploy/systemd/cafe-*.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now cafe-queue cafe-reverb
```

Scheduler (hourly low-stock check is registered):

```cron
* * * * * cd /var/www/cafe && php artisan schedule:run >> /dev/null 2>&1
```

## 8. HTTPS

- Terminate TLS at Nginx/Apache or a proxy; set `APP_URL=https://…`.
- Behind a proxy/CDN, make sure the forwarded protocol reaches Laravel
  (`X-Forwarded-Proto`), otherwise URLs and Secure cookies are wrong.
- HSTS (`max-age=31536000`) is sent automatically for HTTPS requests in
  production. Do not enable HTTPS-only until the certificate works.
- Reverb in the browser uses `REVERB_SCHEME=https`, `REVERB_PORT=443` through
  the proxy; the process itself listens on `REVERB_SERVER_HOST:REVERB_SERVER_PORT`.

## 9. Backup & restore

```bash
php artisan app:backup                        # storage/app/backups/cafe-backup-<time>.tar.gz (mode 0600)
php artisan app:restore path/to/backup.tar.gz # destructive; asks for confirmation unless --force
```

The archive contains a full `mysqldump` and `storage/app/public` (images),
**not** `.env`. Schedule a nightly backup and copy archives off the server:

```cron
30 3 * * * cd /var/www/cafe && php artisan app:backup >> /dev/null 2>&1
```

Test a restore on a separate database at least once.

## 10. Upgrade & rollback

Upgrade (keep the previous release directory until verified):

```bash
php artisan down
php artisan app:backup                       # always before migrating
# deploy the new code (new release dir or git pull), keep .env and storage/
composer install --no-dev --optimize-autoloader
npm ci && npm run build                      # or ship prebuilt public/build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
systemctl restart php8.3-fpm cafe-queue cafe-reverb   # adjust names
php artisan up
```

This release's migrations:
- add refund settlement columns, `orders.idempotency_key`, per-item
  `order_items.inventory_consumed_at` (backfilled from the order);
- add a **one-open-session-per-table** constraint. If the database already
  contains two open sessions for the same table the migration stops and
  lists the tables — merge or close them in **Tables → Session**, then run
  `migrate` again. Nothing is changed automatically.

Rollback: `php artisan down`, restore the previous release directory, then
either `php artisan migrate:rollback --step=4` (this release's four
migrations are reversible) or restore the pre-upgrade backup, then `up`.

## 11. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `strict_types declaration must be the very first statement` in `index.php` | A modified root `index.php`. Replace it with the shipped one. |
| Blank page / 500 right after upload | `storage/` or `bootstrap/cache/` not writable; `vendor/` missing (run `composer install`); `.env` missing `APP_KEY`. |
| Images 404/403 | `php artisan storage:link` not run, or an old root `.htaccess` that blocked `/storage`. |
| Settings "don't save" | Fixed in this release (business settings row on MySQL). Upgrade. |
| No live updates; console says real-time disabled | Assets were built without Reverb keys → `php artisan app:ensure-reverb-credentials && npm run build`. Reverb process not running → start `cafe-reverb`. |
| Dockets don't print | Printer IP/port unreachable from the server; queue worker not running; see failed print jobs on the order and reprint. |
| `/install` redirects to `/` | The app is already installed (lock file or existing users). This is intentional. |
| Migration "Cannot add the one-open-session-per-table constraint" | Close/merge the listed tables' extra open sessions, re-run `migrate`. |
| `Call to undefined function bcmul()` | Install/enable the `bcmath` extension. |
