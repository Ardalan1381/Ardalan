# Risk register

Every finding was checked against the actual source. "Reproduced" means a
test failed on the original code before the fix; the same test passes now.
Severity: **C**ritical (money/stock/data loss or takeover), **H**igh, **M**edium, **L**ow.

## Previous audit findings (AUD-01 … AUD-10)

| ID | Finding | Verified? | Sev | Status | Evidence |
|---|---|---|---|---|---|
| AUD-01 | Concurrent order transitions repeat side effects | **Reproduced on MariaDB**: 6 simultaneous confirmations → 5 succeeded, stock consumed 5×; 6 completions → loyalty awarded 4× | C | Fixed: order row locked, status re-validated under the lock; loyalty earn skips a rule already earned for the order | `tests/Concurrency` `aud01_*` |
| AUD-02 | Concurrent payments overpay | **Reproduced**: 2 of 6 full-amount payments accepted; same for confirming pending payments | C | Fixed: every payment write locks order → payment (one lock order everywhere, incl. gateway callback/refunds); confirm-later check is net of refunds | `aud02_*` |
| AUD-03 | No checkout idempotency | **Reproduced**: same request ×6 → 6 orders | H | Fixed: client UUID key, UNIQUE column, payload fingerprint, replay returns the original order; `cart.js` sends one key per attempt | `aud03_*`, `CheckoutIdempotencyTest` |
| AUD-04 | Adding items to an in-flight order breaks inventory | **Reproduced**: all items deleted & re-created (ids lost, split allocations cascade-deleted, new items never consumed, cancel over-reversed). Also found: a discount's **free item was re-billed at full price** on every edit, and lines of deleted products were silently dropped | C | Fixed: rows kept, only priced columns updated; new rows consumed once; refuses after a bill split / with deleted-product lines | `OrderInventoryIntegrityTest` |
| AUD-05 | Cancellation reverses with the current recipe | **Reproduced**: recipe edited 18 g → 30 g after confirmation, cancel gave back 30 g ×qty | C | Fixed: reversal nets the stock-movement ledger per order item; consumption tracked per item | `OrderInventoryIntegrityTest` |
| AUD-06 | Conflicting open table sessions | **Reproduced**: InnoDB gap-lock deadlock (`SQLSTATE 40001`) for concurrent dine-in checkouts; at READ COMMITTED duplicates are possible | H | Fixed: parent table row locked; DB constraint one-open-session-per-table (virtual column on MySQL, partial index elsewhere) | `aud06_*`, `TableSessionConstraintTest` |
| AUD-07 | Discount usage limit exceeded | **Reproduced**: `usage_limit=1` redeemed 4× (hidden in the first attempt because workers shared a product row lock) | H | Fixed: limits re-checked on the locked discount row with locking reads; per-customer limit counts orders | `aud07_*` |
| AUD-08 | Split-bill reconciliation | **Reproduced**: a pending split payment confirmed later never updated its part; `bill_split_part_items` cascade-deleted with order items | H | Fixed: `BillSplitPart::recalculate()` derives totals from payments − completed refunds on every transition; items are no longer deleted (AUD-04); conservation asserted in tests | `RefundSettlementTest` |
| AUD-09 | Refund marked completed on record creation | **Confirmed** by reading: every refund `completed`, including card/online/store-credit refunds no gateway call ever made | C | Fixed: cash refunds complete immediately; others are `pending` (amount reserved, not yet deducted) until staff confirm/fail with external reference (`refunds.resolve`) | `RefundSettlementTest` |
| AUD-10 | Profit report inaccuracies | **Reproduced**: pending/in-progress orders counted as sales; COGS from today's recipe × today's average cost; float arithmetic; UTC day boundaries for a Tehran business | H | Fixed: completed orders only; COGS from recorded consumption cost (estimate flagged); bcmath; refunds summary; business-timezone periods | `ProfitAnalyticsAccuracyTest` |

## New findings from this work

| ID | Finding | Sev | Status |
|---|---|---|---|
| AUD-NEW-01 | `BusinessSetting::current()` used `firstOrCreate(['id'=>1])` with non-fillable `id`; on MySQL after any rolled-back insert **every request created a new settings row and saved settings were never read** (~120 MariaDB test failures, invisible on SQLite) | C | Fixed + regression test |
| AUD-NEW-02 | Installer protected only by a lock file in `storage/`; losing it on redeploy re-opened `/install/database`, letting anyone **repoint `.env` at their own database** (takeover) | C | Fixed: a DB with users counts as installed (only the session that created the owner may finish); verified live on Apache |
| AUD-NEW-03 | Mellat callback `SaleOrderId` taken from the request without matching the payment (could verify a different, cheaper sale) | H | Fixed + test |
| AUD-NEW-04 | Zibal verify response amount not compared with the payment | M | Fixed + test |
| AUD-NEW-05 | Demo seeders create `test@example.com` + 5 staff with password `password`; nothing stopped them in production | H | Fixed: refuse in production + test |
| AUD-NEW-06 | Dependency advisories (symfony/http-foundation ReDoS CVE-2026-108082, league/commonmark ×2, 5 npm build-tool advisories) | H | Patched within constraints |
| AUD-NEW-07 | Offline printer on `QUEUE_CONNECTION=sync` → HTTP 500 after the status change had committed (also aborted the demo seeder) | M | Fixed + test |
| AUD-NEW-08 | A JS bundle built without `VITE_REVERB_APP_KEY` throws on load and breaks every script (cart, POS) | H | Fixed: real-time becomes optional |
| AUD-NEW-09 | KDS never refreshes without Reverb; showed float minutes | M | Fixed: 20 s fallback refresh, whole minutes, urgency |
| AUD-NEW-10 | Fresh deploy did not redirect to `/install` (README said it does) | L | Fixed |
| AUD-NEW-11 | Session cookie not Secure by default on HTTPS; no HSTS | M | Fixed (follows `APP_URL`; HSTS on HTTPS in production) |
| AUD-NEW-12 | Backup archive (full DB dump) world-readable by default umask | M | Fixed (0600) |
| AUD-NEW-13 | Shared-hosting root `index.php` fatal (production log) and root `.htaccess` that could expose `.env` when rewrite is bypassed | H | Fixed: correct entry point; `.htaccess` denies dotfiles/secrets/internals (verified 403 on Apache) |
| AUD-NEW-14 | `Money::format` divided money into a float | L | Fixed (integer-exact) |

## Open risks (not fixed — see DECISIONS.md / KNOWN_LIMITATIONS.md)

| ID | Risk | Sev | Why open |
|---|---|---|---|
| OPEN-01 | Online refunds cannot be executed through any gateway API; staff settle them externally and confirm in the app | M | Integrating Zarinpal/Zibal/Mellat refund APIs requires merchant contracts and sandbox credentials not available here |
| OPEN-02 | A gateway may capture money for a payment the app then refuses as an overpayment (two concurrent online payments for one order) — the callback shows a failure page, money must be reversed manually | M | Needs a business rule (auto-refund vs. credit); logged in DECISIONS D-05 |
| OPEN-03 | Historical orders consumed **before** this release have no per-item cost/consumption history: their COGS shows as "estimated", and items deleted by the old add-items code cannot be reversed | M | Data never recorded; cannot be reconstructed truthfully |
| OPEN-04 | Sales report (`ReportService`) still counts any non-cancelled order as a sale (profit report counts completed only) | L | Changing the main sales semantics needs owner approval (D-03) |
| OPEN-05 | `salesByPeriod` groups by UTC calendar date | L | Needs DB-specific timezone SQL; boundaries themselves are fixed |
| OPEN-06 | No automated browser (E2E) tests in CI; rendering was verified manually with Playwright | L | Tooling decision |
