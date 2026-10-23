# Implementation plan

Order of work, as executed, and what remains. Integrity before features,
features before visuals.

| # | Phase | Scope | State |
|---|---|---|---|
| 0 | Baseline | Import verbatim, install, run everything on SQLite **and** MariaDB, record results | Done |
| 1 | Critical integrity | AUD-01…AUD-10 reproduced with failing tests (multi-process on MariaDB where locking matters), then fixed | Done |
| 1b | Security & deployment findings | Installer takeover, gateway callbacks, sessions/HSTS, backups, demo seeders, dependency advisories, shared-hosting entry point, `.env.example`, storage skeleton, fonts | Done |
| 2 | Functional completion | Installation lifecycle verified end to end; offline-printer & no-Reverb resilience; fresh-site redirect | Partly done — see "Next" |
| 3 | UI | Rendering audit (Playwright, 3 viewports), KDS/POS/navigation/overflow fixes, tokens & base accessibility rules | Done for operational screens; full visual redesign of every CRUD screen **not** done |
| 4 | Security review | Covered within 1b; see RISK_REGISTER.md | Done for the listed areas |
| 5 | Performance | Not measured in this pass | Not started |
| 6 | Quality gates | CI workflow (lint, audit, 3 PHP versions, MariaDB + concurrency, migration rollback) | Done |
| 7 | Release | Installation/deployment/operator guides, release notes, limitations, source archive | Done |

## Next (recommended order)

1. **Owner decisions** in `DECISIONS.md` (D-01, D-02, D-03, D-04, D-05, D-08).
2. Store-credit balance (currently a refund method with nothing behind it) — either implement a ledger like loyalty points or remove the option.
3. Gateway refund APIs (Zarinpal/Zibal) once merchant sandbox access exists, feeding `ResolveRefundAction` automatically.
4. Performance pass with real data volumes: measure reports (`ReportService`, profit) and the orders index; add indexes from `EXPLAIN` evidence only.
5. Visual redesign of the remaining CRUD screens on top of the existing components (forms, filters, tables), one module per PR with screenshots.
6. Product time-based availability (missing).
7. Browser E2E tests in CI for checkout, POS order, KDS advance.
