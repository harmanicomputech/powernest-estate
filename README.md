# PowerNest Estate

**One Estate. One Platform. Everything Connected.**

A mobile-first estate operating platform for residents, landlords, estate managers, security, facility staff and finance teams — with the PowerNest prepaid-electricity service built in.

- Visitor passes (QR + 6-digit code) verified server-side at the gate
- Prepaid electricity via Stron, paid with Payaza or Paystack, with safe recovery and reconciliation
- Service charges, invoices, receipts and an append-only ledger
- Maintenance, assets, facilities, incidents, announcements, staff tasks
- Multi-estate tenancy, per-estate roles, audit log, installable PWA

Stack: Laravel 13 · PHP 8.3 · MySQL/MariaDB · Livewire 4 · Alpine · Tailwind CSS 4 · Vite.

## Quick start (local)

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite   # or configure MySQL in .env
php artisan migrate --seed       # demo estate, all passwords "password"
php artisan serve
```

Sign in as `resident@powernest.test`, `guard@powernest.test` or `manager@powernest.test`.

`INTEGRATIONS_LIVE=false` (the default) blocks every real payment and vending call.

## Docs

- [Architecture](docs/ARCHITECTURE.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Legacy PowerNest migration inventory](docs/LEGACY_INVENTORY.md)
- [Progress & outstanding work](PROGRESS.md)
