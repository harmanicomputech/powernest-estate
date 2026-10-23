# PowerNest Estate — Progress

_Last updated: 2026-10-23_

## Done (implemented, tested)

| Area | Status |
|---|---|
| Legacy inspection & migration inventory | `docs/LEGACY_INVENTORY.md` |
| Laravel 13 app, MySQL/MariaDB schema (55 tables), migrations + rollback | Verified on SQLite and MariaDB 10.11 |
| Auth: register, login (throttled), email verification, password reset, 2FA (TOTP), suspension | Fortify |
| Multi-estate tenancy, per-estate roles (12 roles + platform admin), permission gates, policies | Tested incl. cross-estate 404s |
| Estates, buildings, properties, occupancies/history, household members, invitations, access requests, move requests | |
| Visitor passes (one-time / multi-entry / recurring), QR + 6-digit code, share link, revoke | Tested |
| Gate console (camera QR + manual), atomic admit, deny, walk-ins with host approval, check-out, shifts, expected/inside lists | Tested (Livewire + browser) |
| PowerNest: meters (Stron lookup), purchase state machine, Paystack + Payaza adapters, webhooks, vend job, reconciliation console, manual token / retry / refund, token email, support tickets | 25 provider-mocked tests |
| Estate billing: charge types, bulk billing per period, invoices, online pay, manual payments, receipts, ledger, 4-eyes waivers, void, reminders | Tested |
| Expenses & vendors with approval | |
| Maintenance: requests with photos, assignment, schedule, statuses, internal notes, costs, resident confirmation; assets + preventive orders | Tested |
| Facilities & bookings (overlap-safe), incidents (restricted), announcements (estate/building, emergency notify), staff tasks | Tested |
| Notifications (in-app centre + email, preferences), audit log (append-only) | |
| Role dashboards, power operations, monthly reports — all from live queries | |
| PWA: manifest, icons, conservative service worker, offline page | |
| Safety & facility inspection checklists (failed items raise a maintenance request) | Tested |
| Lost & found | Tested |
| Estate payment reversals (ledger offset, invoice reopened, approver only) | Tested |
| Facility booking fees billed as estate invoices; voided on cancellation | Tested |
| Property document library (private storage, shared/internal visibility) | Tested |
| Data retention pruning for visits, pass scans, expired passes, webhook payloads | Tested |
| Yearly estate management & security levy with "paid for the year" status on the resident dashboard | Tested |
| Offline payments: residents upload a receipt; finance verifies, approves (any amount up to balance) or rejects with a reason; four-eyes enforced | Tested |
| Transactions page (estate payments, receipts under review, electricity) and PDF downloads for invoices, payment receipts and electricity receipts | Tested |

**Tests:** 88 tests / 442 assertions passing on SQLite and on MariaDB (`php artisan test`).

## Outstanding / not yet built

- **Payaza status-query endpoint must be confirmed** (`PAYAZA_VERIFY_URL`); until then Payaza payments need manual confirmation by a finance approver.
- Legacy data import (users, meters, transactions) — needs the live database dump.
- SMS / WhatsApp delivery (only email + in-app; WhatsApp share links for passes).
- Offline gate verification (intentionally not built; needs a separate signed-pass design).
- Gateway-initiated refunds (reversals are recorded; the money itself is refunded outside the app).
- Subscription billing for estates.

## Useful commands

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed     # local demo estate "Greenfield Court" (password: "password")
php artisan serve
php artisan test --compact
php artisan power:reconcile
```

Demo logins (local only): resident@, landlord@, tenant@, household@, guard@, supervisor@, facility@, tech@, finance@, manager@, admin@, owner@, platform@ — all `@powernest.test`.
