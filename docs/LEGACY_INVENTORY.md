# Legacy PowerNest — Migration Inventory

Source inspected: `admin.zip` (uploaded archive of the production cPanel account `app.powernest.com.ng`).
No secret values are reproduced in this document.

## 1. Which code is authoritative

The archive contains three copies of the application:

| Path | Status |
|---|---|
| `/` (root) | **Authoritative.** Newest mtimes (config May 2026, services Sep 2026, webhook log entries to Oct 2026). |
| `/working/` | Older snapshot (Aug 2025). Identical structure, stale. |
| `/working/backup/`, `/working/backup/new/`, `/backup/` | Older backups — retire. |

The SQL dumps (`database/powertokens.sql`, `database.sql`, `database_setup.sql`) **do not match the code**:
the dump defines `user.password`, `meter`, `status enum('pending','completed','failed')`, while the live code reads
`user.password_hash`, `meter_info`, `power_token`, `transaction.service_charge`, `transaction.power_amount`,
`transaction.token_data`, status `'success'`. The real production schema was evolved by ad-hoc `ALTER`s and is only
recoverable from the live database. Inferred live schema:

- `user` (id, username, email, password_hash, first_name, last_name, phone_number, is_active_user, created_at, last_login)
- `meter_info` (id, user_id, meter_number, meter_name, address, is_favorite, created_at)
- `transaction` (id, user_id, transaction_id `TRX-…`, payment_reference `REF-…`, amount = total, power_amount, service_charge,
  meter_number, status pending|success|failed, payment_method, payment_data, token_data, error_message, token_generated_at, timestamps)
- `power_token` (id, transaction_id → transaction.id, meter_id, token_number, meter_number, units, details JSON, created_at)
- `admin` (id, username, email, password, full_name, role super_admin|admin, last_login), `admin_activity_log`, `settings`

## 2. Existing functionality

Customer: register/login (username or email), forgot/reset password (email link), profile, saved/favourite meters,
meter lookup (Stron `QueryMeterInfo`), buy electricity (amount + fixed ₦250 service charge), Payaza inline checkout
(default) / Paystack redirect (disabled), token display on transaction page, transaction history, token email via Brevo SMTP.

Admin: separate `admin` table login, dashboard stats, users, meters, transactions (+CSV exports), admin accounts, settings, activity log.

**Actual live purchase flow** (confirmed from `error_log`, ~1,600 Stron vend calls):

1. `buy.php` inserts `transaction` (pending) and redirects to `payaza-checkout.php`.
2. Browser runs Payaza Checkout SDK (`checkout-v2.payaza.africa/js/v1/bundle.js`, `connection_mode: "Live"`).
3. The browser JS callback decides "success", POSTs to `save-transaction.php` which marks the transaction `success` **without any server verification**.
4. Browser redirects to `generate-token.php?ref=&transaction_id=&meter_number=&amount=` which **calls Stron `VendingMeter` with the meter and amount taken from the query string**.

Payaza webhooks *are* arriving (`webhooks/webhook.log`, ~800 events: `transaction_status: "Funds Received"`, `status: "Completed"`,
`merchant_reference: REF-…`, `amount_received`, `transaction_fee`, channel mostly `VirtualAccount`) but the webhook handlers
never process them (they expect `event == 'transaction_successful'`, which the real payload doesn't contain, and call an undefined
`verify_webhook_signature()`). The root `webhook.php` references non-existent `transactions`/`tokens` tables.

## 3. External services and configuration variables

| Integration | Legacy config keys | Contract observed in code | New env vars |
|---|---|---|---|
| Stron vending | `stron_api_url`, `stron_company`, `stron_username`, `stron_password` | `POST {url}/VendingMeter` JSON `{CompanyName, UserName, PassWord, MeterID, is_vend_by_unit:"false", Amount}` → array `[{Token, Total_unit, Meter_id, Meter_type, Customer_name, Customer_address, Price, Rate, Gen_time, …}]`; `POST {url}/QueryMeterInfo` `{CompanyName, UserName, PassWord, MeterId}` → array with `Meter_id`, `Customer_name`… **Plain HTTP endpoint.** | `STRON_BASE_URL`, `STRON_COMPANY`, `STRON_USERNAME`, `STRON_PASSWORD`, `STRON_TIMEOUT` |
| Payaza | `payaza_api_key` (public, `PZ78-PKLIVE-…`), `payaza_secret_key` (`PZ78-SKLIVE-…`), `payaza_webhook_secret` (empty), `payaza_api_url` | Inline SDK with public key. Server verify function (`/v1/transaction/verify`) exists but is never used and is not a documented Payaza endpoint. | `PAYAZA_PUBLIC_KEY`, `PAYAZA_SECRET_KEY`, `PAYAZA_ENVIRONMENT`, `PAYAZA_VERIFY_URL`, `PAYAZA_WEBHOOK_SECRET` |
| Paystack | `paystack_api_key` (live secret; a test key is commented out) | `POST /transaction/initialize`, `GET /transaction/verify/{ref}`, HMAC-SHA512 webhook signature | `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY` |
| SMTP (Brevo) | `smtp_host`, `smtp_port`, `smtp_encryption`, `smtp_username`, `smtp_password`, `email_from`, `email_from_name` | Hand-written SMTP client in `functions.php` | `MAIL_*` |
| MySQL | `$db_config` | host/user/pass/db | `DB_*` |
| Pricing | `power_unit_rate` 285, min 1,000 / max 50,000, service charge 250 (hard-coded in `buy.php`) | | Estate settings (DB) |

## 4. Credentials — status

| Credential | Found | Notes |
|---|---|---|
| Stron company/username/password | Yes | **Exposed**: also written in plain text to `error_log` on every vend. **Rotate.** |
| Payaza live public key | Yes | Public by design. |
| Payaza live secret key | Yes | **Exposed** in archive. **Rotate.** |
| Payaza webhook secret | **Missing** (empty) | Blocker for authenticated webhooks — see §7. |
| Paystack live secret key | Yes | **Exposed. Rotate.** Paystack public key missing (only needed for inline checkout; redirect flow doesn't need it). |
| Brevo SMTP user/password | Yes | **Exposed. Rotate.** |
| MySQL | Yes | Username == password == db name. **Rotate.** |
| Legacy admin | Seeded `admin`/`admin123` hash in `admin_tables.sql` | Do not migrate. |

The archive itself (and `error_log`, 14 MB, containing Stron credentials, customer emails, tokens) must be treated as leaked material.

## 5. Security and reliability problems (must not be carried over)

Critical
1. **Free electricity**: `generate-token.php` vends any `amount` to any `meter_number` from the query string for any logged-in user; no payment verification, no link to a paid transaction, no idempotency.
2. **Client-asserted payment**: `save-transaction.php` marks payments successful based on browser JSON (no auth check, no CSRF).
3. **Wrong vend amount**: the live path vends the *total incl. service charge*, while callbacks vend `power_amount` — inconsistent.
4. Webhooks ack 200 before processing, have no working signature check, never match the real payload; payment confirmations are effectively lost.
5. No uniqueness on `power_token.transaction_id`; check-then-insert races allow double vending on repeated callbacks.
6. Paystack webhook signature computed but helper name mismatched (always rejected) — or would trust any request if fixed incorrectly.

High
7. Secrets in source; `debug => true` with `display_errors` in production; SQL/stack traces shown to users.
8. Credentials, tokens and full provider payloads logged to `error_log` / `webhook.log` (publicly reachable path on cPanel unless denied).
9. Stron called over plain HTTP; meter validation disables TLS verification; validation falls back to a **fake "Test Customer" success** on bad responses.
10. No CSRF tokens on any form; no login throttling; session fixation (no `session_regenerate_id` on login).
11. 15+ SMTP/debug/test scripts (`smtp_*`, `test_*`, `email_debug.php`, `paytest.php`, `demo_payment.php`, `syntax_check.php`) deployed in web root.
12. Demo payment fallback: if gateway init fails the user is pushed to a "demo payment" path.
13. Float arithmetic for money; `units` computed client-side for display.
14. Transaction IDs/refs in URLs used as sole authorization (`transaction_details.php?id=`) — needs ownership checks.

## 6. Retain / refactor / replace / retire

| Item | Decision |
|---|---|
| Stron `VendingMeter`, `QueryMeterInfo` contracts | **Retain** contract, re-implement as `StronVendingClient` (HTTP client, timeouts, redacted logging, no fake fallbacks). |
| Payaza inline checkout (public key + reference) | **Retain** UX; confirmation moves server-side (webhook-triggered + status query). |
| Paystack initialize/verify/webhook | **Retain** contract, re-implement with amount/currency checks + HMAC verification. |
| `REF-…` payment references | Replace with `PNE-…` references generated server-side (unique index). |
| Saved/favourite meters | Retain; meters now attach to an authorized property in an estate. |
| ₦250 service charge, min/max | Retain as **estate settings** (defaults 250 / 1,000 / 50,000). |
| Fixed `power_unit_rate` | Retire for authoritative use — units come from Stron's response only. |
| Token email | Retain via Laravel Mail + queue (Brevo SMTP). |
| Admin panel, `admin` table | Replace by platform/estate roles in the unified user model. |
| Legacy user/transaction data | Import later via a one-off command once the live DB dump is provided (§7). |
| Demo payment, test/debug scripts, backups, `save-transaction.php`, `generate-token.php` | **Retire.** |

## 7. Ambiguities and blockers

1. **Payaza server verification endpoint** — the legacy code never used a working one, and Payaza's docs were not reachable from the build sandbox. The adapter targets the documented *Transaction Status Query* style (`Authorization: Payaza <base64 public key>`, `X-TenantID`), URL configurable via `PAYAZA_VERIFY_URL`. **Must be confirmed with a sandbox call before go-live.** Until it is verified, Payaza payments stay `awaiting_verification` and can be confirmed by a finance officer through the audited reconciliation screen.
2. **Payaza webhook authenticity** — no webhook secret/signature scheme is configured. New code treats an unsigned Payaza webhook only as a *hint* that triggers a server-side status query; it never confirms payment by itself.
3. **Stron transaction lookup** — no query-by-reference endpoint is used in legacy code, so a vend that times out cannot be auto-resolved. Such purchases go to `reconciliation_required` and are never auto-revended.
4. **Payaza fee** — webhooks show `request_amount = power + ₦250 + ₦100 transaction_fee`. New code accepts payment when `amount_received ≥ expected total` and records the fee; confirm with Payaza whether fees are customer-borne.
5. **Live DB dump** not included — legacy user/meter/transaction import is deferred.
