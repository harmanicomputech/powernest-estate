# Deployment Guide

## Requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `intl`, `gd`, `openssl`, `curl`, `fileinfo`
- MySQL 8.0+ or MariaDB 10.6+ (verified on MariaDB 10.11)
- Node 20+ (build step only), Composer 2
- HTTPS (required: secure cookies, camera access for QR scanning, PWA install)
- A process supervisor for the queue worker and cron for the scheduler

## First deploy

```bash
git clone … && cd powernest-estate
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env            # then fill in values — see below
php artisan key:generate
php artisan migrate --force
php artisan storage:link        # not required for documents (private disk) but harmless
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan powernest:create-admin   # platform super administrator
```

Then sign in as the platform admin → **Platform console → Onboard estate**, which sends the estate owner an invitation.

Never run `db:seed` in production (the demo seeder refuses to run there anyway).

## Environment

Production essentials:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
QUEUE_CONNECTION=database   # or redis
INTEGRATIONS_LIVE=true      # only on the production server
```

Integration variables (names only; values come from the provider dashboards — **rotate every credential that appeared in the legacy archive first**):

| Variable | Source |
|---|---|
| `STRON_BASE_URL`, `STRON_COMPANY`, `STRON_USERNAME`, `STRON_PASSWORD` | Stron vending account |
| `PAYSTACK_ENABLED`, `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY` | Paystack dashboard → Settings → API keys |
| `PAYAZA_ENABLED`, `PAYAZA_PUBLIC_KEY`, `PAYAZA_SECRET_KEY`, `PAYAZA_ENVIRONMENT=live` | Payaza dashboard |
| `PAYAZA_VERIFY_URL` | Payaza Transaction Status Query endpoint, with `{reference}` placeholder — **confirm with Payaza** |
| `PAYAZA_WEBHOOK_SECRET` | Long random string you generate |
| `MAIL_*` | SMTP (legacy used Brevo) |

Webhook URLs to register with providers:

- Paystack: `https://APP_URL/webhooks/paystack`
- Payaza: `https://APP_URL/webhooks/payaza/{PAYAZA_WEBHOOK_SECRET}`

## Workers and scheduler

```
# cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1

# supervisor
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Scheduled: `power:reconcile` (5 min), `passes:expire` (15 min), `billing:remind` (daily 08:00 Lagos), failed-job pruning.

Electricity tokens are issued by queued jobs — **if the worker is down, paid purchases wait in "Payment confirmed"** and are resumed by `power:reconcile` once it runs.

## Go-live checklist

1. Rotate all legacy credentials (Stron, Paystack, Payaza, SMTP, DB).
2. Confirm the Payaza status-query contract with a sandbox transaction; set `PAYAZA_VERIFY_URL`.
3. Make one small live purchase per gateway with a staff meter and check: token issued, email received, entry in Power operations.
4. Disable / delete the legacy PHP app and its `error_log`, `webhook.log`, test scripts and backups from the web server.
5. Enable two-factor authentication for every staff account.

## Backups and restore

- Nightly `mysqldump --single-transaction --routines` of the database, encrypted and stored off-server; keep 30 daily + 12 monthly.
- Back up `storage/app/private/documents` (maintenance photos, incident evidence) with the same retention.
- `APP_KEY` must be backed up securely: electricity tokens and visitor-pass secrets are encrypted with it and are unrecoverable without it.
- Restore drill: load dump into a staging DB, set the same `APP_KEY`, run `php artisan migrate --force`, sign in, open a token receipt to confirm decryption.

## Data protection (NDPA 2023)

Automatic pruning (daily `model:prune`), configurable via `RETENTION_VISITS_MONTHS` (default 12), `RETENTION_PASS_SCANS_MONTHS` (6), `RETENTION_VISITOR_PASSES_MONTHS` (12) and `RETENTION_WEBHOOK_EVENTS_MONTHS` (12). Incidents, ledger, payments and the audit log are never pruned automatically (suggested: 6 years). Confirm periods with the estate's DPO. Personal data in provider payloads is masked before storage; passwords and tokens are never logged.
