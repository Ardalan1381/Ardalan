# Baseline report

Phase 0 record of the source exactly as received, before any change. Every
later change is a separate commit on top of the baseline commit, so the
original can always be diffed against.

## Source archive

| Item | Value |
|---|---|
| File | `CafeMenu-Claude.zip` (uploaded twice, identical) |
| SHA-256 | `0244fdd8f812b7a57117693545e2428baf0f4e113121a2f802c5a10962822462` |
| Integrity | `unzip -t`: no errors |
| Files | 698 |
| Baseline commit | `48edb74` "Import CafeMenu source snapshot (baseline, unmodified)" — the archive imported verbatim under `CafeMenu/` |

### Present
Laravel 12 application: `app/` (57 models, 46 admin controllers, 69 admin form
requests, 28 policies, actions/services), `bootstrap/`, `config/`,
`database/` (77 migrations, 3 seeders, 5 factories), `lang/` (fa, en, ar),
`resources/` (Blade, Tailwind 4 CSS, 19 JS modules), `routes/` (web, console,
channels), `tests/` (66 test files), `deploy/` (nginx, systemd, supervisor),
`composer.json/.lock`, `package.json/-lock.json`, `phpunit.xml`, `README.md`,
`CLAUDE.md`, `cafe.md` (product brief).

### Expected exclusions (not treated as missing features)
`vendor/`, `node_modules/`, `.git/`, real `.env`, caches, logs, uploads,
databases, `public/build/`.

### Genuinely missing (fixed)
| Missing | Impact | Resolution |
|---|---|---|
| `.env.example` | README, installer docs and `composer setup` all reference it | Written (`.env.example`); defaults that work before a DB exists |
| `storage/` directory tree | Laravel cannot boot without it | Standard skeleton with `.gitignore` files |
| `bootstrap/cache/` | Laravel cannot boot without it | Added with `.gitignore` |
| Vazirmatn font files | `public/fonts/README.md` asked for them; Persian fell back to Tahoma | Vazirmatn 33.0.3 woff2 (400/500/600/700) + OFL licence bundled |
| Root `index.php` | The production server log shows a hand-made one fatals: *"strict_types declaration must be the very first statement"* | Correct shared-hosting entry point + hardened root `.htaccess` |

## Environment used for verification

| Component | Version |
|---|---|
| OS | Ubuntu 24.04 (container) |
| PHP | 8.3.6 CLI + Apache 2.4 mod_php 8.3 |
| PHP extensions | all required incl. `bcmath` (had to be installed — the app requires it), `gd`, `intl`, `pdo_mysql`, `pdo_sqlite` |
| Composer | 2.8.12 |
| Node / npm | 22.22.0 |
| Database | SQLite (in-memory, default test DB) and **MariaDB 10.11.14** (disposable local server) |
| Browser | Chromium (Playwright) for rendering checks |

Credentials used anywhere in this work are dummy values for disposable local
databases (`cafe`/`cafe`). No production system was touched.

## Baseline results (unmodified source)

| Check | Result |
|---|---|
| `composer validate` | valid |
| `composer install` (lockfile) | OK |
| `npm ci && npm run build` | OK |
| `php artisan --version` | Laravel 12.69.1 |
| `php artisan route:list` | 222 routes |
| PHPUnit, SQLite, before building assets | 128 failed / 469 passed — Vite manifest missing (environment, not code) |
| PHPUnit, SQLite, after build, without bcmath/Reverb keys | 30 failed — `bcmul()` undefined, Reverb key null (environment) |
| **PHPUnit, SQLite, complete environment** | **597 passed / 0 failed** |
| **PHPUnit, MariaDB 10.11** | **~120 failed** — real defect, see AUD-NEW-01 in the risk register |
| `vendor/bin/pint --test` | 25 files with style violations |
| `composer audit` | 3 advisories (symfony/http-foundation CVE-2026-108082 ReDoS; 2× league/commonmark) |
| `npm audit` | 5 advisories in build-time tooling (vite, follow-redirects, shell-quote, source-map-js) |

The MariaDB result is the most important baseline finding: production runs on
MySQL/MariaDB, but the shipped test configuration only ever ran SQLite, which
masked a defect that made saved business settings silently ineffective.

## Results after this work

See `PROGRESS.md` for the per-phase log and `TESTING.md` for how to
reproduce. Summary at the time of writing:

| Check | Result |
|---|---|
| PHPUnit, SQLite | 637 passed, 8 skipped (the concurrency suite, which needs MySQL/MariaDB) |
| PHPUnit, MariaDB 10.11 (incl. 8 multi-process concurrency tests) | 645 passed, 0 failed |
| Pint | clean |
| composer audit / npm audit | no advisories |
| Web installer end-to-end on Apache + MariaDB | completed; lock + production `.env` written |
| Playwright render, 15 pages × 3 viewports (fa/RTL) | all 200, no horizontal overflow, no JS errors |
