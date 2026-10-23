# Testing

## Suites

| Suite | Path | Runs on |
|---|---|---|
| Unit | `tests/Unit` | any |
| Feature | `tests/Feature` | SQLite (default) or MySQL/MariaDB |
| Concurrency | `tests/Concurrency` | **MySQL/MariaDB only** (skips itself on SQLite) |

## Prerequisites

PHP 8.2+ with `bcmath`, `gd`, `intl`, `pdo_sqlite`, `pdo_mysql`; Composer;
Node 18+. Feature tests render Blade views that include Vite assets, so build
them once:

```bash
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate && php artisan app:ensure-reverb-credentials
```

## Fast run (SQLite, in memory)

```bash
php artisan test
```

## Full run on MariaDB/MySQL (what production uses)

Use a **disposable** database — the concurrency tests run `migrate:fresh`.

```sql
CREATE DATABASE cafemenu_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cafe'@'127.0.0.1' IDENTIFIED BY 'cafe';
GRANT ALL ON cafemenu_test.* TO 'cafe'@'127.0.0.1';
```

```bash
DB_CONNECTION=mariadb DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=cafemenu_test DB_USERNAME=cafe DB_PASSWORD=cafe \
php artisan test
```

Environment variables win over `phpunit.xml`'s SQLite defaults. Only the
concurrency suite: add `--testsuite=Concurrency`.

## How the concurrency tests work

`tests/Concurrency/ConcurrencyTestCase::race()` starts N separate PHP
processes (`tests/Concurrency/worker.php`), each with its own database
connection, releases them at the same instant, and collects one JSON result
per worker. The scenarios (`Scenarios.php`) call the real actions — no
mocking. Each test then asserts the business invariant (stock consumed
once, never overpaid, one order per idempotency key, …). These tests failed
on the original code (see RISK_REGISTER.md) and pass now.

Because they commit real rows, they rebuild the schema before and after each
test so nothing leaks into the transactional tests.

## CI

`.github/workflows/cafemenu.yml` (repository root): lint/static checks
(composer validate, `php -l`, Pint, composer audit), the suite on PHP
8.2/8.3/8.4 with SQLite, and on MariaDB 10.11 with migrate → rollback →
migrate → fresh plus the concurrency suite.

## Conventions

- Tests pin `APP_LOCALE=en` (`phpunit.xml`); assertions use English copy.
- Never assume the default DB driver in a test; set it explicitly when the
  behaviour depends on it (see `BackupRestoreTest`).
- Never run DDL inside a transactional test on MySQL — it commits implicitly.
