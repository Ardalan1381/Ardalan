# UI verification

Rendering was checked in a real browser, not inferred from templates.

**Setup:** the app served by Apache 2.4 + mod_php 8.3 (document root = project
root, i.e. the shared-hosting layout through the root `.htaccess`), MariaDB
10.11 seeded with `DemoDataSeeder`, Persian locale. Playwright (Chromium)
logged in as the owner and captured 15 screens at three viewports: desktop
1440×900, tablet 1024×768, mobile 390×844.

Screens: public menu, cart, dashboard, POS tables, POS counter, KDS, orders,
order detail, tables, products, inventory, customers, payments, reports,
business settings.

## Findings and outcome

| Before | After |
|---|---|
| A JS exception on **every page** (`You must pass your app key when you instantiate Pusher`) — a bundle built without Reverb keys disabled cart/POS scripts | No JS errors on any page; real-time disabled with a console warning instead |
| Menu product images 403 (root `.htaccess` blocked `/storage`) | Images load |
| KDS: `1.9238748333333 دقیقه پیش`; order numbers wrapped mid-token; 4 cramped columns beside the sidebar on a tablet | Whole minutes, urgency colours, focus mode (full width), no wrapping |
| POS table showed "available" and "occupied" together | One state per table |
| "Popular"/"New"/"Special" in English inside the Persian UI | Translated |
| Inventory page overflowed horizontally on mobile | No horizontal overflow on any of the 45 captures |
| 22 ungrouped sidebar links | Six labelled groups |
| Stock shown as `2000.0000` | `2,000` |

Automated regressions for these live in
`tests/Feature/Admin/OperationalScreensTest.php`; representative
screenshots are in `docs/engineering/screenshots/`.

## Not verified

- Real thermal printer output and real payment-gateway pages.
- Live WebSocket delivery (Reverb process was not run in the verification environment).
- Screen-reader behaviour beyond semantic markup and focus visibility.
