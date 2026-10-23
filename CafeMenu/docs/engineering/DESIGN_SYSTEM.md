# Design system

The UI keeps the existing stack — Blade components + Tailwind CSS 4 + small
vanilla-JS modules. No new frontend framework or UI library was added.

## Principles

1. **Persian first.** `dir="rtl"` from the locale; use logical utilities
   (`ms-*`, `me-*`, `ps-*`, `start-*`, `text-start`, `border-s-*`) — never
   `ml-*`/`left-*`. Mixed-direction tokens (order numbers, references,
   phone numbers) are wrapped in `dir="ltr"`.
2. **Operational screens are fast and calm.** No decorative animation on
   POS/KDS; large touch targets (≥ 44 px, `min-h-11`) for primary actions;
   one clear state per object (a table is *free*, *occupied* or *out of
   service* — never two at once).
3. **Accessible by default.** Visible `:focus-visible` ring in the brand
   colour on every interactive element; status is never colour-only (badges
   carry text); `prefers-reduced-motion` honoured globally.
4. **Numbers line up.** Tables use tabular numerals; money is integer-exact
   (`App\Support\Money`), quantities trimmed (`App\Support\Quantity::display`).

## Tokens (`resources/css/app.css`)

| Token | Use |
|---|---|
| `--color-primary`, `--color-secondary` | Brand colours, set at runtime from business settings (`@theme inline`) |
| `--color-success/-subtle`, `--color-warning/-subtle`, `--color-danger/-subtle`, `--color-info/-subtle` | Semantic status; dark-mode variants under `.dark` |
| `--font-sans` | Vazirmatn (bundled, 400/500/600/700) → system fallbacks |

Dark mode is class-based (`.dark` on `<html>`, toggled by `x-theme-toggle`).

## Components (`resources/views/components`)

`button`, `input`, `select`, `textarea`, `checkbox`, `radio`, `switch`,
`table` (scroll-contained on small screens), `badge` (`variant` or `status`),
`card`, `stat-card`, `alert`, `toast-container`, `modal`, `dropdown`,
`empty-state`, `skeleton`, `nav-link`, **`nav-group`** (new: labelled sidebar
section that hides itself when the user may see none of its links),
`locale-switcher`, `theme-toggle`, `translation-fields`, `recipe-editor`.

## Layout patterns

- **Admin layout** — sidebar grouped into *Operations, Menu & tables,
  Customers & marketing, Inventory, Reports & audit, Settings*. Off-canvas
  drawer below `md`; Escape closes it.
- **Focus mode** — a screen declares `@section('focus-mode', '1')` (KDS, POS)
  and the sidebar stays a drawer up to `2xl`, giving tablets the full width.
- **Page header** — title + actions in a `flex-wrap` row so actions wrap
  instead of overflowing on phones.

## KDS card anatomy

Order number (LTR, no wrap) · table or order type · elapsed whole minutes ·
urgency border (neutral → amber at ⅔ of the delay threshold → red when
delayed, plus a "Delayed" text label) · items with quantity chips · options ·
highlighted notes · one full-width primary action.
