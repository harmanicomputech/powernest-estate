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

**Tests:** 75 tests / 322 assertions passing on SQLite and on MariaDB (`php artisan test`).

## Outstanding / not yet built

- **Payaza status-query endpoint must be confirmed** (`PAYAZA_VERIFY_URL`); until then Payaza payments need manual confirmation by a finance approver.
- Legacy data import (users, meters, transactions) — needs the live database dump.
- SMS / WhatsApp delivery (only email + in-app; WhatsApp share links for passes).
- Lost & found and safety-inspection checklist UIs (lost & found table exists).
- Offline gate verification (intentionally not built; needs a separate signed-pass design).
- Online collection of facility booking fees; refunds/reversals for estate payments.
- General property document library (uploads exist for maintenance and incidents).
- Automated retention/pruning jobs for visit logs and webhook payloads.
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
