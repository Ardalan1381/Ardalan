# Release notes — 2026.10 (integrity & deployment release)

Built on the uploaded CafeMenu source; existing features, routes and data are
preserved. Upgrade notes are in `docs/deployment/INSTALLATION.md` §10.

## Fixes that affect money and stock
- Concurrent confirmations no longer consume stock several times; concurrent completions no longer award loyalty points several times.
- Concurrent or retried payments can no longer overpay an order; confirming a pending payment counts refunds correctly.
- Retried/double-clicked checkouts create exactly one order.
- Discount usage limits hold under concurrent checkouts.
- Adding items to an order in progress keeps existing lines intact, consumes stock only for the new lines, no longer bills a free (discount) item at full price, and is refused after a bill split.
- Cancelling an order gives back exactly the stock that was taken, even if the recipe changed since.
- Split-bill parts stay in sync with their payments through every payment state.
- Refunds: cash refunds complete immediately; card/online/store-credit refunds wait for staff confirmation (new **Confirm refunded / Mark not refunded** controls on the order page).
- Profit report: completed orders only, historical cost of goods, refunds summary, business-timezone date ranges.
- **Business settings now save correctly on MySQL/MariaDB** (previously could silently create a new settings row on every request).

## Security
- Installer cannot be re-opened on a live site if the lock file is lost.
- Payment callbacks: Mellat sale must belong to the payment; Zibal amount must match.
- Secure session cookies by default on HTTPS; HSTS in production.
- Backups written owner-only; demo seeders refuse to run in production.
- Patched dependency advisories (symfony/http-foundation, league/commonmark, build tools).
- Root `.htaccess` for shared hosting denies `.env`, dotfiles and application internals.

## Deployment
- Shared hosting: correct root `index.php` (fixes *"strict_types declaration must be the very first statement"*); upload → installer works without any shell command.
- `.env.example` included; `storage/` skeleton included; Vazirmatn font bundled.
- Offline printer no longer breaks the request on the sync queue; KDS auto-refreshes without Reverb; a bundle built without Reverb keys no longer breaks page scripts.
- `deploy/build-release.sh` builds a self-contained release archive.
- CI workflow for lint, audits, PHP 8.2–8.4, MariaDB and concurrency tests.

## Interface
- Kitchen display: whole minutes, urgency colours, larger text, highlighted notes, full width on tablets.
- POS: one clear state per table, full width on tablets.
- Grouped admin navigation; translated product labels; no horizontal overflow on phones; visible keyboard focus; reduced-motion support.

## Database migrations in this release
`2026_10_23_100000` refund settlement columns · `100001` one open session per
table (stops if duplicates exist — nothing is changed automatically) ·
`100002` order idempotency key · `100003` per-item consumption marker
(backfilled). All four are reversible.
