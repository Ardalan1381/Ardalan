# Feature inventory

Built from the actual source (routes, controllers, actions, migrations,
tests), not from the product brief. Status legend:

- **Working** — implemented end to end and covered by automated tests (and, where marked 🖥, rendered and checked in a browser).
- **Working (fixed)** — existed but had a defect fixed in this work.
- **Partial** — usable, with a stated gap.
- **Missing** — not implemented.

| Area | Capability | Status | Notes |
|---|---|---|---|
| Public menu | Categories, products, options/add-ons, search, labels, images, multi-language (fa/en/ar), per-locale reference prices | Working 🖥 | Labels were untranslated (fixed) |
| | QR per table / per language | Working | `TableQrTest` |
| | Waiter call from table | Working | `WaiterCallTest` |
| Cart & checkout | Server-side pricing, order types (dine-in/takeaway/pickup/delivery), cash on pickup, rate limiting | Working (fixed) 🖥 | Idempotency added (AUD-03) |
| | Online payment (Zarinpal, Zibal, Mellat) | Working (fixed) | Callback hardening (AUD-NEW-03/04). Real gateway credentials never tested — sandbox/merchant accounts required |
| | Order tracking page with live status | Working | Live only when Reverb runs |
| POS | Table grid by section, table order, counter order, customer lookup, add items, notes | Working (fixed) 🖥 | Contradictory table badge, focus mode (fixed) |
| | Split bill (by item / amount / even), split payments | Working (fixed) | AUD-08 |
| | Table transfer / session merge | Working | Session-level; no per-order merge/split between tables |
| Kitchen (KDS) | Columns by status, advance status, delay threshold | Working (fixed) 🖥 | Float minutes, urgency, refresh fallback (fixed) |
| Order lifecycle | Explicit state machine, history with actor, rejection/cancellation | Working (fixed) | AUD-01 |
| Payments | Cash, card, online, pay-later, mixed; independent payment status; overpayment guard | Working (fixed) | AUD-02 |
| Refunds | Full / partial, append-only, permission-gated | Working (fixed) | Settlement lifecycle (AUD-09). **Store credit is recorded but no store-credit balance exists** to spend it — staff must issue it manually |
| Shifts & cash | Open/close, cash in/out, expected vs actual | Working | `ShiftEngineTest` |
| Invoices | Numbering, templates, immutable once issued, print view | Working | `InvoiceGenerationTest` |
| Printing | ESC/POS network printers, print routes by category, retries | Working (fixed) | Sync-queue failure (AUD-NEW-07). Only network (TCP 9100) printers |
| Customers | Profiles, groups, order history, stats | Working | |
| Discounts | % / fixed / item / half-price / BxGy / free item; product, category, customer, group, day, date, time, min order/qty, campaign; priority, stacking, usage limits | Working (fixed) | AUD-07; free-item re-billing on edit (AUD-04) |
| Loyalty | Rules (spend threshold, buy X get points), rewards, ledger, redemption | Working (fixed) | Duplicate award (AUD-01). No customer tiers |
| Inventory | Items, units with conversion, categories, opening balance, min/max, low-stock alert (hourly), movement ledger | Working | |
| | Recipes / BOM for products and add-ons; consumption on confirm; reversal on cancel | Working (fixed) | AUD-04/05 |
| | Suppliers, purchases, partial receiving, purchase returns, adjustments, stock counts, waste | Working | `PurchasingAndStockCountTest`, `InventoryEngineTest` |
| | Stock transfers between locations | Missing | Single-location product by design |
| Reports | Sales by period, popular products, tables, payments, categories, staff, shifts, stock valuation, waste, purchases, top customers | Working | Sales report semantics unchanged (DECISIONS D-03) |
| | Profit / COGS | Working (fixed) | AUD-10 |
| Access control | Roles (owner, manager, cashier, waiter, kitchen, barista), permissions, policies, per-link sidebar visibility | Working | `RolePermissionSeederTest` |
| Audit log | Financial/stock/settings events with old/new values | Working | |
| Notifications | New order / waiter call, real-time via Reverb | Working | Requires Reverb process |
| Installer | Requirements, DB, migrate, owner, business, lock, production `.env` | Working (fixed) 🖥 | Lost-lock takeover (AUD-NEW-02); verified end to end on Apache + MariaDB |
| Backup / restore | `app:backup`, `app:restore` | Working (hardened) | Not run against a real dump in this environment's CI (needs mysqldump); archive now 0600 |
| Localization | fa (RTL), en, ar; Jalali dates | Working | Money uses Latin digits (D-08) |
| Product scheduling | Time-based availability windows per product | **Missing** | Listed in the brief; only manual `is_available` |
| Customer tiers | Tier-based pricing/discounts | **Missing** | Customer groups + group discounts cover part of it |
