# Known limitations

Stated plainly so nobody relies on something that is not there.

## Not implemented
- **Gateway refunds**: no Zarinpal/Zibal/Mellat refund API is called. Online refunds must be made in the gateway panel and then confirmed in the app.
- **Store credit**: offered as a refund method, but there is no store-credit balance to spend; issue it manually.
- **Product schedules** (time-based availability), **customer tiers**, **multi-location stock transfers**.
- **Performance work**: not measured or tuned in this release; reports run synchronously.
- **Full visual redesign** of every CRUD screen: operational screens (KDS, POS, navigation, mobile layout) were improved; other screens keep the existing (consistent) style.

## Verified only partially
- Payment gateways: covered by tests with faked HTTP/SOAP responses; **never run against real merchant accounts** here.
- Thermal printing: socket-level tests only; no physical printer.
- Real-time (Reverb): configuration and channel authorisation tested; live delivery not exercised end to end.
- `app:backup` / `app:restore` against a real `mysqldump`: argument handling and guards tested; a full backup→restore cycle was not run in CI.
- Browser rendering was checked with Playwright manually; there are no automated E2E tests in CI.

## Behaviour to be aware of
- Orders consumed **before** this release have no recorded consumption cost; their profit rows show "estimated cost". Lines that the previous add-items code deleted cannot be reversed on cancellation.
- The operational sales report still counts every non-cancelled order; the profit report counts completed orders only (see `docs/engineering/DECISIONS.md` D-03).
- Money is displayed with Latin digits in the Persian UI (D-08).
- A prebuilt release archive has real-time updates off until assets are rebuilt with the server's Reverb key.
- On shared hosting without a queue worker, use `QUEUE_CONNECTION=sync`; without Reverb, live updates fall back to refresh.

## Business decisions awaiting the owner
See `docs/engineering/DECISIONS.md` (D-01 refunds, D-02 add-items pricing, D-03 revenue recognition, D-04 cost method, D-05 overpaying online payments, D-08 digits).
