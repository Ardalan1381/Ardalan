# Cafe Menu

A self-hosted digital menu and restaurant ordering system for cafes, restaurants, fast-food businesses, coffee shops, and food courts. One installation serves one business — there is no multi-tenancy, no licensing, and no required payment gateway.

Built with Laravel 12, MySQL/MariaDB, Blade, Tailwind CSS, vanilla JavaScript, and Laravel Reverb for real-time updates.

## Requirements

- PHP 8.2, 8.3, 8.4, or 8.5
- MySQL 8+ or MariaDB 10.6+
- Node.js 18+ (for building frontend assets)
- Composer 2

Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `gd`, `fileinfo`, `curl`, `bcmath`. The installer's requirements page checks all of these automatically.

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan app:ensure-reverb-credentials
```

Create the database and a user matching your `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), then either:

- run `php artisan migrate` and `php artisan db:seed --class=Database\\Seeders\\RolePermissionSeeder` directly, or
- visit `/install` in the browser and complete the guided installer (recommended — it also creates the owner account and business settings).

Run everything needed for local development (web server, queue worker, Reverb, log viewer, and Vite) in one command:

```bash
composer run dev
```

This starts, in parallel: `php artisan serve`, `php artisan queue:listen`, `php artisan pail`, `npm run dev`, and `php artisan reverb:start`.

## Installer

A fresh installation is not usable until `/install` is completed. The installer:

1. Checks PHP version, required extensions, and writable directories (`storage/`, `bootstrap/cache/`, `.env`).
2. Collects database credentials, tests the connection, and writes them into `.env`.
3. Runs migrations and seeds the default roles/permissions (Owner, Manager, Cashier, Waiter, Kitchen, Barista).
4. Creates the initial Owner account.
5. Collects basic business configuration (name, currency, locale, timezone).
6. Writes a lock file (`storage/app/private/installed.lock`) and can never be run again while that file exists — visiting any `/install/*` route afterward redirects to the public menu.

No credentials are ever displayed after the account is created; if you lose the Owner password, reset it directly in the database or build a `php artisan tinker` one-off, then rotate it from the admin panel.

## Production deployment

### First time on a fresh server

Nothing about the web installer replaces normal PHP app deployment — get the code running first, *then* visit `/install` to finish setup through the browser. (On shared hosting without a shell, upload a release built with `deploy/build-release.sh`: the first request creates `.env` and `APP_KEY` itself — see `docs/deployment/INSTALLATION.md`.)

```bash
# 1. Get the code onto the server (git clone / rsync / release archive), then:
composer install --no-dev --optimize-autoloader

cp .env.example .env
php artisan key:generate              # required — the installer's requirements
                                       # page blocks progress until this is set
php artisan app:ensure-reverb-credentials
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache

# .env must exist and have real REVERB_* values (both done above) before this —
# npm/Vite bakes VITE_REVERB_APP_KEY into the compiled bundle at build time,
# not read at runtime, so building first (or before generating credentials)
# silently ships a build that can never receive real-time updates.
npm install && npm run build
```

Point your web server's document root at `public/` (see `deploy/nginx/cafe.conf` for a full example), start PHP-FPM, start the two background processes below, then visit the site — you'll land on `/install` automatically until it's completed.

The installer disables debug mode and sets `APP_ENV=production` itself once you finish the last step, so a non-technical buyer running only the browser-based installer still ends up with a safe configuration without ever having to edit `.env` by hand for that part. You still need to set a real `APP_URL` (https) and, once HTTPS is confirmed working end to end, `SESSION_SECURE_COOKIE=true` — see `.env.example` for every other variable a production install may need (mail, Iranian payment gateways, Reverb).

### Two background processes must run continuously

Alongside PHP-FPM/the web server — the web installer and `php artisan serve` cannot start or supervise these for you:

- **Queue worker** — processes notifications and any other queued work (`QUEUE_CONNECTION=database` by default; no Redis required).
- **Reverb** — the WebSocket server powering real-time order/waiter-call notifications. Without it, notifications are still *created*, they just never arrive live in the admin panel.

Both must be run and restarted by a process supervisor, not launched manually. Ready-to-use unit files are in `deploy/`:

- `deploy/systemd/cafe-queue.service` + `deploy/systemd/cafe-reverb.service` — for systemd (`systemctl enable --now cafe-queue cafe-reverb` after copying them into `/etc/systemd/system/`)
- `deploy/supervisor/cafe.conf` — the equivalent Supervisor config, if that's what your server already runs

Edit the `User`/`WorkingDirectory`/`ExecStart` (or `command`/`directory`) paths in whichever one you use to match your server before installing it. Put Reverb behind your reverse proxy (`deploy/nginx/cafe.conf` includes the WebSocket proxy block) rather than exposing its port directly, and make sure `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` in `.env` match whatever the public-facing proxy terminates.

### Cron

Laravel's scheduler needs one cron entry (it runs the hourly low-stock check, `inventory:check-low-stock`):

```cron
* * * * * cd /var/www/cafe && php artisan schedule:run >> /dev/null 2>&1
```

### File storage

Product/category/logo images are stored on the `public` disk (`storage/app/public`, symlinked via `php artisan storage:link`). Ensure that symlink survives deployments (recreate it in your deploy script if `public/storage` doesn't persist).

### Backup and restore

```bash
php artisan app:backup                     # writes storage/app/backups/cafe-backup-<timestamp>.tar.gz
php artisan app:restore path/to/backup.tar.gz   # destructive — prompts for confirmation unless --force is passed
```

A backup bundles a `mysqldump` of the database with a copy of `storage/app/public` (product/category/logo images). It requires the `mysql`/`mysqldump` client binaries on the server and only supports the `mysql`/`mariadb` database connections. **The archive deliberately does not include `.env`** — keep a separate, secure copy of `APP_KEY` yourself, since without it the encrypted `payment_gateway_credentials` column (and any other encrypted data) cannot be read back after a restore. Consider scheduling `app:backup` via the same cron entry used for `schedule:run`, or a separate nightly cron line, and copying the resulting archive off-server.

### After deploying new code

Re-run `composer install --no-dev`, `npm run build`, `php artisan migrate --force`, then:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Then restart PHP-FPM and the two supervised processes so they pick up the new code.

## Testing

```bash
php artisan test            # SQLite in memory (fast)
./vendor/bin/pint --test
```

The suite also runs against MySQL/MariaDB — what production uses — including
multi-process race tests in `tests/Concurrency`. Point `DB_*` environment
variables at a **disposable** database; see `docs/engineering/TESTING.md`.
CI (`.github/workflows/cafemenu.yml`) runs both.

## Documentation

| | |
|---|---|
| Installation, shared hosting, HTTPS, backups, upgrades, troubleshooting | `docs/deployment/INSTALLATION.md` |
| Release notes / known limitations | `docs/RELEASE_NOTES.md`, `docs/KNOWN_LIMITATIONS.md` |
| Operator guides (Persian) | `docs/guides/ADMIN_GUIDE.md`, `POS_OPERATOR_GUIDE.md`, `KITCHEN_GUIDE.md` |
| Engineering: baseline, architecture, features, risks, decisions, plan, progress, testing, design system | `docs/engineering/` |

## License

Proprietary — built for a specific self-hosted deployment. Not licensed for redistribution.
