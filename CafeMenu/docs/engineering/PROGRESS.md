# Progress log

Newest first. Each entry lists what was inspected, changed, tested, and what
remains. Commit hashes refer to branch `claude/bold-keller-57ppp9`.

## Phase 7 — Release preparation
- **Changed:** `docs/deployment/*`, `docs/guides/*`, `RELEASE_NOTES.md`, `KNOWN_LIMITATIONS.md`, README; release archive script `deploy/build-release.sh`.
- **Tests:** full suite SQLite + MariaDB (see below).

## Phase 3 — UI (rendering audit and operational fixes)
- **Inspected:** 15 screens × 3 viewports in Chromium against Apache + MariaDB + demo data (fa/RTL).
- **Changed:** `layouts/admin.blade.php` (groups, focus mode, a11y labels), `components/nav-group.blade.php`, `admin/kitchen/index.blade.php`, `admin/pos/*`, menu/POS product labels, 29 admin headers (wrap), `resources/js/bootstrap.js` (optional Echo), `resources/css/app.css` (focus ring, tabular numerals, reduced motion, Vazirmatn 600), `Money`, `Quantity::display`, `Order::minutesInCurrentStatus`.
- **Tests added:** `OperationalScreensTest`, `QuantityDisplayTest`, Money exactness.
- **Open:** full redesign of remaining CRUD screens; Persian digits (D-08).

## Phase 1b — Security & deployment
- **Changed:** `EnsureNotInstalled`, `RedirectToInstaller`, `InstallController`, Mellat/Zibal gateways, `config/session.php`, `SetSecurityHeaders`, `BackupApplication`, seeders, `PrintDocumentJob`, root `index.php` + `.htaccess`, `.env.example`, storage skeleton, fonts, dependency patches, CI workflow, Pint.
- **Verified live:** installer end to end on Apache + MariaDB; lost-lock protection; `.env`/`composer.json`/`vendor`/`storage/logs` → 403.

## Phase 1 — Critical integrity (AUD-01…AUD-10)
- **Method:** a failing test first for every finding; multi-process MariaDB harness for races.
- **Changed:** order transition, payment record/confirm/callback, checkout idempotency, add-items, recipe consumption/reversal, table sessions, discount engine, split parts, refunds lifecycle, profit analytics, report periods. 4 migrations (refund settlement columns, one-open-session constraint, idempotency key, per-item consumption).
- **Result:** all 8 concurrency tests and all new feature tests pass; each failed on the original code (see RISK_REGISTER.md for the observed failures).

## Phase 0 — Baseline
- See `BASELINE_REPORT.md`. Found AUD-NEW-01 (settings never saved on MySQL) while running the suite on MariaDB; fixed first.

## Current test status
| Run | Result |
|---|---|
| SQLite | 637 passed, 8 skipped (concurrency suite needs MySQL) |
| MariaDB 10.11 | 645 passed, 0 failed (147 s) |
