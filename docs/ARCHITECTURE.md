# PowerNest Estate — Architecture

Modular Laravel 13 monolith (PHP 8.3+, MySQL 8 / MariaDB 10.6+), Blade + Livewire 4 + Alpine (bundled with Livewire), Tailwind CSS 4, Vite, Fortify for authentication.

## Layout

| Path | Responsibility |
|---|---|
| `app/Enums` | Roles, permission catalogue, and every status (with labels, UI tones and allowed transitions). |
| `app/Models` | Eloquent models. Estate-scoped models use `Concerns\BelongsToEstate`. |
| `app/Support/EstateContext` | The verified estate the user is operating in (request-scoped). |
| `app/Http/Middleware` | `ResolveEstateContext`, `EnsureAccountIsActive`, `EnsurePlatformAdmin`, `SecurityHeaders`. |
| `app/Policies` | Record-level authorization (passes, purchases, invoices, payments, maintenance, incidents, bookings, documents). |
| `app/Services/*` | Business logic: `Visitors\{PassService,GateService}`, `Power\{PowerPurchaseService,MeterService}`, `Billing\{BillingService,BillingPaymentService}`, `Operations\*`, `Estate\{MembershipService,OccupancyService}`, `Audit\AuditLogger`, `Dashboard\DashboardMetrics`. |
| `app/Integrations` | Provider adapters only: `Payments\{PaystackGateway,PayazaGateway,GatewayManager}`, `Vending\StronVendor`, `OutboundGuard`. |
| `app/Http/Controllers` | Thin controllers: validate, authorize, call a service, render. `Manage\*` = estate staff, `Platform\*` = platform admin, `Power\*` = resident electricity. |
| `app/Livewire` | `GateConsole`, `PurchaseStatus`, `NotificationBell`. |
| `resources/views/components` | Design system (`ui/*`), layouts, icons, brand. |

## Tenancy and authorization

- **Estate context is never read from request input.** `ResolveEstateContext` takes the estate id from the session (set only by the switch endpoint after a membership check) and re-verifies an *active* membership in an *active* estate on every request, including Livewire updates (persistent middleware).
- It runs **before route-model binding**; `BelongsToEstate::resolveRouteBinding` constrains every estate-scoped model to the current estate, so a foreign id in a URL is a 404.
- Roles are per estate (`estate_memberships`: user × estate × role, many rows allowed). `EstateRole::permissions()` maps roles to the `Permission` catalogue; each permission is registered as a Gate (`@can('power.reconcile')`).
- Platform super-admin (`users.is_platform_admin`) is separate: it grants the platform console only, not access to any estate's residents, visitors or money.
- Policies add ownership checks on top (e.g. only the purchaser or power staff see a token; only reporter/assignee/incident managers see an incident).

## PowerNest electricity (state machine)

```
pending_payment ─▶ paid ─▶ vending ─▶ token_issued
     │  ▲            ▲        ├─▶ vend_failed ───────────┐ (staff: retry / record token / refund)
     │  └ expired    └────────┤   (vendor answered, no token)
     ├─▶ payment_failed        └─▶ reconciliation_required ┘ (timeout, 5xx, amount/currency mismatch, crash)
```

- Amount, service charge and total are computed server-side from estate settings (integer kobo). The browser sends only meter id, naira amount and gateway choice.
- Payment is confirmed **only** by a server-side verification (`PaymentGateway::verify`) that checks reference, gateway, currency and `amount ≥ total`. Browser callbacks and webhooks only *trigger* verification.
- Paystack webhooks require a valid HMAC-SHA512 signature. Payaza webhooks (no signature scheme available) are accepted only on a secret URL and are hints; confirmation still comes from the status query.
- Every webhook body is stored once (`gateway_webhook_events.fingerprint` unique) with redacted payload.
- Vending happens after commit, in `VendElectricity`. `PowerPurchaseService::vend()` moves `paid → vending` under a row lock before calling Stron, so duplicate jobs/callbacks cannot vend twice; `power_tokens.power_purchase_id` is unique as the final guard.
- Vend outcomes: `success` → token; `failed` (vendor answered, no token) → `vend_failed`; `not_sent` (DNS/connect error — request never left) → back to `paid` and auto-retried; `unknown` (timeout/5xx/garbage) → `reconciliation_required`, **never auto-retried**. Staff must confirm in the Stron portal before retrying or record the token manually.
- `power:reconcile` (every 5 min) re-verifies recent unpaid purchases, expires abandoned ones (a late payment is still honoured), resumes paid ones and flags purchases stuck mid-vend.
- `INTEGRATIONS_LIVE=false` blocks every outbound provider call at a single choke point (`OutboundGuard`).
- Tokens are encrypted at rest; provider payloads are whitelisted + redacted before storage.

## Visitor access

- Pass = random 256-bit token (QR payload `PNE:<token>`) + 6-digit code. Only `sha256(token)` and `hmac(estate:code)` are queryable; plain values are stored encrypted for re-display. Codes are freed when a pass stops being active.
- `GateService::check()` is read-only and logs every scan; `admit()` re-evaluates under a row lock and uses a conditional `UPDATE … WHERE entries_count < max_entries` so simultaneous scans cannot both admit.
- Rules evaluated server-side: status, window, entry limit, recurring day/time in the estate timezone, and that the host is still an active occupant.
- Walk-ins notify linked residents for approval; supervisors can override (audited).

## Money and records

- All amounts are integer kobo; `Money::parse()` parses user input with string arithmetic.
- Estate billing writes an append-only `ledger_entries` row for every invoice, payment, adjustment and void; ledger and audit models throw on update/delete. Discounts/waivers and expenses require a different approver (four-eyes).
- Electricity purchases (`PNE-` refs, `power_purchases`) and estate payments (`PNB-` refs, `payments`) share gateway adapters but never share records.

## Frontend / PWA

- Mobile-first shell: bottom bar shaped by the user's job (resident vs gate officer vs staff), sidebar on desktop, navigation filtered by permission.
- Design tokens live in `resources/css/app.css` (`@theme`); rebrand by editing the brand/gold/canvas scales.
- `public/sw.js` caches only hashed build assets and icons; navigations are network-only with a static offline page. Nothing private, financial or security-relevant is cached, and no writes are queued offline. Authenticated responses carry `Cache-Control: no-store`.
