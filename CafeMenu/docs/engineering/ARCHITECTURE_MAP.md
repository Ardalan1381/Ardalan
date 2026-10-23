# Architecture map

A single-business, self-hosted Laravel 12 monolith. Server-rendered Blade +
Tailwind 4; small vanilla-JS modules per screen (bundled by Vite); MySQL /
MariaDB; database queue; Laravel Reverb for WebSocket push.

```
browser ──HTTP──> public/index.php ─> web middleware
                                       (security headers, installer redirect, locale)
                                    ─> Controllers (thin)          app/Http/Controllers
                                         └─ FormRequests (validation + authorize)
                                         └─ Policies / permissions  app/Policies
                                    ─> Actions (one use case each) app/Actions
                                    ─> Services (domain engines)   app/Services
                                    ─> Eloquent models             app/Models
                                    ─> MySQL/MariaDB
        <──WebSocket── Reverb <── broadcast events (OrderPlaced, OrderStatusChanged, notifications)
queue worker ──> jobs (PrintDocumentJob → ESC/POS network printers), notifications
scheduler ──> inventory:check-low-stock (hourly)
```

## Domains and their entry points

| Domain | Write paths (Actions/Services) | Notes |
|---|---|---|
| Ordering | `PlaceOrderAction` (public checkout), `CreateStaffOrderAction` (POS), `AddItemsToOrderAction`, `TransitionOrderStatusAction` | Prices always re-resolved server-side by `Orders\CartPricer`. State machine in `TransitionOrderStatusAction::TRANSITIONS`. |
| Pricing & discounts | `Discounts\DiscountEngine`, `DiscountEligibilityChecker`, `DiscountCalculator` | Runs inside the order transaction; usage limits enforced on a locked row. |
| Tables | `TableSessionService` | One open session per table, enforced by lock + DB constraint. |
| Payments | `RecordPaymentAction`, `UpdatePaymentStatusAction`, `PaymentCallbackController` + `Payments\Gateways\{Zarinpal,Zibal,Mellat}` | Lock order: **order row → payment row**, in every writer. Callback verifies server-to-server. |
| Refunds | `ProcessRefundAction`, `ResolveRefundAction` | Append-only `refunds`; payments never mutated. |
| Split bills | `Billing\CreateBillSplitAction`, `Billing\RecordSplitPaymentAction`, `BillSplitPart::recalculate()` | Part totals are derived, never patched. |
| Inventory | `Inventory\StockMovementService` (single choke point), `RecipeConsumptionService`, actions for purchases, receipts, adjustments, counts, waste, returns | `stock_movements` is the ledger; `current_stock` is a cache. |
| Loyalty | `Loyalty\LoyaltyLedgerService`, `EarnLoyaltyForOrderAction`, `RedeemLoyaltyRewardAction` | Ledger-style, never negative. |
| Shifts / cash | `Shifts\*` actions, `RecordCashMovementAction` | Cash movements recorded from cash payments/refunds when the actor has an open shift. |
| Invoices & printing | `Invoices\*`, `Printing\PrintRoutingService`, `PrintDocumentJob` | Dockets queued after commit; failures never affect the transition. |
| Reports | `ReportService`, `Reports\*Service` | Read-only. Profit semantics in DECISIONS D-03/D-04. |
| Installer | `InstallController`, `EnsureNotInstalled`, `RedirectToInstaller`, `Installer\{InstallLock,EnvWriter,InstallerRequirements}` | |
| Audit | `AuditLogger` | Called from actions for financial/stock events. |
| Backup | `app:backup`, `app:restore` commands | mysqldump + public media; `.env` deliberately excluded. |

## Concurrency model

All money/stock invariants are protected by **pessimistic row locks inside
one DB transaction**, always taken in the same order to avoid deadlocks:

1. `orders` row (status transitions, payments, refunds, add-items)
2. `payments` row / `bill_split_parts` row / `refunds` row
3. `discounts`, `inventory_items`, `customers` rows (taken by the engines)

Unique constraints back the locks where a row may not exist yet:
`orders.idempotency_key`, one open session per table, `payments(gateway, gateway_token)`,
`bill_split_part_items.order_item_id`.

## Data model highlights

- Money: unsigned big integers in minor units, everywhere.
- Quantities: decimal strings, bcmath with scale 4 (`App\Support\Quantity`).
- Snapshots: order items keep product name/price; order keeps customer name/phone.
- Ledgers: `stock_movements`, `loyalty_ledger_entries`, `cash_movements`, `refunds`, `order_status_history`, `audit_logs`.

## Frontend

`resources/views/layouts/{app,admin,install,print}.blade.php`; reusable
components in `resources/views/components` (button, input, select, table,
badge, card, modal, dropdown, nav-link, nav-group, stat-card, empty-state,
toast, …). Tokens and base rules in `resources/css/app.css`. Page scripts in
`resources/js/*.js`, entry points listed in `vite.config.js`. See
`DESIGN_SYSTEM.md`.
