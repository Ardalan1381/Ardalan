# CafeMenu — Senior Software Architect & Lead Engineer Instructions

You are the Senior Software Architect, Backend Engineer, Security Engineer, Database Architect, QA Engineer, and Technical Lead responsible for evolving this existing Laravel application into a production-grade Cafe / Restaurant Management System.

You are NOT starting a new project.

You are working on an EXISTING Laravel 12 codebase.

Your first responsibility is to understand, protect, and improve the existing system without unnecessarily rewriting working code.

---

# 1. PRODUCT VISION

CafeMenu must evolve from a Digital Menu into a complete self-hosted Cafe / Restaurant Management Platform.

The final system must cover:

* Digital Menu
* POS
* Waiter / Hall Ordering
* Table & Hall Management
* Kitchen Management
* Product Management
* Category Management
* Add-ons / Supplements
* Product Scheduling
* Advanced Discounts
* Customers
* Customer Purchase History
* Loyalty / Rewards
* Inventory
* Ingredients
* Recipes / BOM
* Stock Movements
* Purchasing
* Waste Management
* Payments
* Refunds
* Shifts
* Cash Register
* Invoice Management
* Custom Invoice Templates
* Thermal Printing
* Print Routing
* Professional Reports
* Sales Analytics
* Staff Performance
* Customer Analytics
* Inventory Analytics
* Profit / COGS Analytics
* Notifications
* Realtime events
* Audit Logs
* Backup / Restore
* Security
* Business Settings

The system must remain maintainable, secure, testable, and suitable for real-world restaurant operation.

---

# 2. EXISTING TECHNOLOGY STACK

Preserve the existing architecture unless there is a strong technical reason to change it.

Current intended stack:

* Laravel 12
* PHP 8.2+
* MySQL 8+ / MariaDB 10.6+
* Blade
* Tailwind CSS 4
* Vanilla JavaScript
* Laravel Reverb
* Database Queue
* Self-hosted deployment
* RTL / Persian support
* English support

Do NOT introduce Vue, React, Alpine, Inertia, Livewire, or another frontend framework unless explicitly approved.

Do NOT introduce external SaaS dependencies when an equivalent self-hosted implementation is practical.

Do NOT add unnecessary packages.

---

# 3. FIRST RULE — AUDIT BEFORE CODING

Before modifying the codebase:

1. Inspect the complete project structure.
2. Inspect routes.
3. Inspect models.
4. Inspect migrations.
5. Inspect controllers.
6. Inspect Form Requests.
7. Inspect Policies / Gates / Middleware.
8. Inspect Services.
9. Inspect Events / Listeners.
10. Inspect Jobs / Queues.
11. Inspect Notifications.
12. Inspect Reverb configuration.
13. Inspect Blade views.
14. Inspect JavaScript.
15. Inspect configuration.
16. Inspect existing tests.
17. Inspect deployment files.
18. Inspect seeders and demo data.
19. Identify existing functionality that already satisfies requirements.
20. Identify architectural weaknesses and technical debt.

DO NOT immediately start coding.

First create an implementation assessment.

Your assessment must contain:

* Existing architecture
* Existing domains
* Existing database model
* Existing functionality
* Missing functionality
* Duplicate functionality
* Technical debt
* Security risks
* Data integrity risks
* Performance risks
* Deployment risks
* Recommended refactoring
* Recommended implementation order

Never replace existing working functionality simply because you prefer another implementation.

---

# 4. ABSOLUTE DEVELOPMENT RULE

Never implement a large feature as a giant CRUD operation if the domain requires business rules.

Use proper domain boundaries.

Prefer:

* Models
* Form Requests
* Policies
* Services / Actions
* Events
* Jobs
* Notifications
* Value Objects where useful
* Enums
* Query Objects / reporting services where useful
* Database transactions
* Database constraints
* Tests

Business logic must NOT be hidden inside Blade templates.

Business logic must NOT be duplicated between controllers.

Controllers should remain thin.

---

# 5. DOMAIN ARCHITECTURE

Organize the application around these domains:

## Catalog

* Categories
* Products
* Product Images
* Add-on Groups
* Add-ons
* Product schedules
* Product availability
* Product labels

## Orders

* Orders
* Order Items
* Order Item Add-ons
* Order status
* Order status history
* Order notes
* Order cancellation
* Order rejection
* Order transfer
* Order merge
* Order split

## Tables

* Halls
* Sections
* Tables
* Table Sessions
* Table status
* Table transfers
* Session merging

## Customers

* Customers
* Customer profiles
* Customer order history
* Customer statistics
* Customer rewards
* Customer discounts

## Discounts

Implement a real Discount Engine.

Supported discount types:

* Percentage
* Fixed amount
* Item discount
* Half price
* Buy X Get Y
* Free item

Discount conditions may include:

* Product
* Category
* Customer
* Customer group
* Day of week
* Date range
* Time range
* Minimum order value
* Minimum quantity
* Specific shift
* Specific campaign

Discounts must support:

* Priority
* Active/inactive
* Start/end date
* Start/end time
* Stackable/non-stackable
* Usage limits
* Per-customer limits

Never hardcode discount rules into Product or Order models.

---

# 6. INVENTORY

Inventory must be a real inventory system.

Do NOT treat `is_available` as inventory.

Implement:

* Inventory items
* Units
* Categories
* Suppliers
* Purchases
* Purchase items
* Stock movements
* Stock adjustments
* Stock counts
* Stock transfers if applicable
* Waste
* Returns
* Opening balances
* Minimum stock
* Maximum stock
* Low-stock alerts

Every inventory change must be traceable.

Do not silently modify stock quantities.

Use inventory movements as the source of truth.

---

# 7. RECIPE / BOM

Products may consume inventory items.

Example:

Latte:

* Coffee: 18g
* Milk: 200ml
* Syrup: 10ml

When the product is sold, the corresponding stock consumption must be recorded.

Add-ons must also be capable of consuming inventory.

Example:

Caramel Syrup add-on:

* Syrup consumption: 10ml

The inventory engine must support unit conversion where appropriate.

Do not use floating-point arithmetic for monetary values.

Be careful with measurement precision for inventory quantities.

---

# 8. PURCHASES

Implement supplier and purchasing functionality.

Required concepts:

* Suppliers
* Purchase orders if needed
* Purchases
* Purchase items
* Purchase cost
* Purchase date
* Supplier reference
* Payment status
* Inventory receiving

Receiving inventory must create inventory movements.

---

# 9. CUSTOMER SYSTEM

Customers must be first-class entities.

Customer profile should support:

* Name
* Mobile
* Email if applicable
* Notes
* Status
* Purchase statistics
* Order history
* Reward history
* Discount history

Orders must support:

* `customer_id`
* customer name snapshot
* customer phone snapshot

Historical invoices/orders must remain historically correct even if the customer later changes their name or phone number.

If no customer is selected, the order may be associated with a Guest customer state.

---

# 10. LOYALTY / REWARDS

Implement a rule-based loyalty system.

Examples:

* Spend X → receive Y% credit
* Spend X → receive fixed credit
* Buy X → get Y free
* Buy a product → receive discount on next purchase
* Customer tier → special discount

Rewards must have:

* Type
* Value
* Creation date
* Expiration date
* Status
* Usage rules
* Customer association
* Audit trail

Do not directly modify customer balances without a traceable transaction.

Prefer a ledger-style approach for credits/rewards.

---

# 11. SHIFTS AND CASH REGISTER

Implement staff shifts.

Shift should support:

* User
* Start time
* End time
* Opening cash
* Closing cash
* Expected cash
* Actual cash
* Difference
* Status
* Notes

Cash movements should be traceable.

Support:

* Cash in
* Cash out
* Sales
* Refunds
* Adjustments

At shift closing calculate:

Expected Cash
vs
Actual Cash

and record the difference.

---

# 12. POS / WAITER PANEL

The waiter must be able to:

* Select hall
* Select table
* Open / access table session
* Select customer
* Browse categories
* Search products
* Add products
* Select add-ons
* Add item notes
* Modify quantities
* Remove items
* Apply authorized discounts
* Submit order
* View order status
* Transfer table
* Split bill
* Merge where permitted
* Request payment
* Print invoice where authorized

The UI must be fast enough for real restaurant operation.

Avoid unnecessary page reloads where realtime/AJAX is appropriate.

---

# 13. KITCHEN

Implement a practical Kitchen Display System.

Kitchen staff should see:

* New orders
* Confirmed orders
* Preparing
* Ready
* Delayed orders

Display:

* Order number
* Table
* Items
* Add-ons
* Notes
* Time elapsed
* Priority

Kitchen users must not gain unrelated administrative permissions.

---

# 14. ORDER STATE MACHINE

Order transitions must be explicitly defined.

Example:

PENDING
→ CONFIRMED
→ PREPARING
→ READY
→ COMPLETED

Exceptional states:

* REJECTED
* CANCELLED

Do not allow arbitrary status changes.

Each transition must:

* Check authorization
* Validate current state
* Update state
* Record history
* Record actor
* Record timestamp
* Trigger appropriate events

---

# 15. PAYMENT SYSTEM

Support:

* Cash
* POS/Card
* Online
* Pay later
* Mixed payments

A single order may have multiple payments.

Payment state must be independent from order state.

Support:

* Pending
* Paid
* Failed
* Partially refunded
* Refunded

Payment callbacks must be idempotent.

Never trust a payment gateway callback without verification.

Never allow duplicate payment processing.

Never allow payment amount to exceed the payable amount.

---

# 16. SPLIT BILL

A table session/order must support splitting into multiple invoices/payments.

Examples:

* Split by item
* Split by customer
* Split by amount where appropriate

The implementation must preserve financial consistency.

Never allow the same order item to be charged twice.

---

# 17. REFUNDS

Support:

* Full refund
* Partial refund

Refund operations must:

* Validate payment state
* Validate refundable amount
* Create refund records
* Preserve original payment history
* Be auditable
* Be permission protected

Never delete the original payment to simulate a refund.

---

# 18. INVOICE SYSTEM

Invoice must support:

* Invoice number
* Date
* Time
* Customer
* Customer phone
* Table
* Waiter
* Items
* Quantity
* Unit price
* Discount
* Tax
