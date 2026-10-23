# Decisions

Business-rule choices made during this work. Each has a default that is
already implemented (so the product is consistent today) and is marked
**needs owner confirmation** where it affects money, stock or reporting.
Nothing here invents a settlement policy: every default is the more
conservative option.

| ID | Decision | Implemented default | Alternatives | Owner? |
|---|---|---|---|---|
| D-01 | When is a refund "completed"? | Cash paid out at the counter (`refund_method=cash`, or original method on a cash payment) completes immediately. Card/online reversals and store credit are `pending` until a staff member confirms (with an external reference) or marks them failed. Pending refunds reserve the amount but do not reduce the order's paid total. | Treat card-terminal refunds as immediate; require a second approver | **Yes** |
| D-02 | Adding items to an order in progress | Allowed while `pending`, `confirmed`, `preparing`. Refused once `ready`/`completed`/`cancelled`/`rejected`, after an invoice, a redeemed loyalty reward, a bill split, or when a line's product was deleted. The whole cart is re-priced with current prices (pre-existing behaviour) so discounts stay correct; existing lines keep their identity. | Keep the original unit price for existing lines | **Yes** (re-pricing existing lines at today's price) |
| D-03 | What is a "sale" in profit analytics? | Completed orders only, created within the period (business timezone). Completed refunds of those orders are reported separately and deducted in the summary; pending refunds shown but not deducted. The operational sales report is unchanged (any non-cancelled/rejected order). | Recognise at payment; align the sales report too | **Yes** |
| D-04 | Cost of goods | The unit cost recorded on each consumption movement at the time of consumption (weighted average cost then). Missing history → current recipe × current average cost, flagged "estimated". | FIFO valuation | **Yes** |
| D-05 | Online payment that would overpay | Refused (consistent with staff payments); the customer sees a verification failure and staff must reverse the gateway capture manually. | Accept and record a credit/auto-refund | **Yes** |
| D-06 | Per-customer discount limit unit | Counted per order (the unit `used_count` uses), not per redemption row. | Per row (previous behaviour, which counted a multi-line discount several times) | Low risk — engineering |
| D-07 | Idempotency key lifetime | Kept with the order permanently; replay of the identical payload returns the original order, a different payload with the same key is rejected (422). The browser forgets the key after success. | Expire after N hours | Engineering |
| D-08 | Persian digits for money | **Not changed.** Money is shown with Latin digits, as the original `money.js` explicitly chose, and receipts share the formatter. | Persian digits on screen only | **Yes** (visual) |
| D-09 | Existing duplicate open table sessions | The new constraint migration stops and lists the tables instead of merging/closing anything. | Auto-close the newest duplicate | Engineering (no data is changed silently) |
