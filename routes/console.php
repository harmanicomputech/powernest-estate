<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PowerPurchase;
use App\Models\PowerToken;
use App\Models\User;
use App\Notifications\InvoiceReminder;
use App\Services\Power\PowerPurchaseService;
use App\Services\Visitors\PassService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

Artisan::command('power:reconcile', function (PowerPurchaseService $service) {
    $stats = $service->sweep();
    $this->info('Power reconciliation: '.json_encode($stats));
})->purpose('Re-verify pending payments, resume paid purchases and flag interrupted vends');

Artisan::command('passes:expire', function () {
    $this->info('Expired '.app(PassService::class)->expireStale().' visitor passes');
})->purpose('Mark visitor passes past their validity window as expired');

Artisan::command('billing:remind', function () {
    $count = 0;
    Invoice::with('billedUser', 'estate')
        ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
        ->whereNotNull('billed_user_id')
        ->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<', now()->subDays(7)))
        ->lazyById()
        ->each(function (Invoice $invoice) use (&$count) {
            $days = (int) $invoice->estate->setting('billing.reminder_days_before_due');
            if ($invoice->due_date->isPast() || $invoice->due_date->lte(now()->addDays($days))) {
                $invoice->billedUser->notify(new InvoiceReminder($invoice));
                $invoice->update(['last_reminded_at' => now()]);
                $count++;
            }
        });
    $this->info("Sent {$count} invoice reminders");
})->purpose('Remind residents about invoices that are due soon or overdue');

Artisan::command('powernest:create-admin', function () {
    $name = text('Name', required: true);
    $email = Str::lower(text('Email', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.'));
    $pass = password('Password (min 12 characters)', required: true, validate: fn ($v) => strlen($v) >= 12 ? null : 'Use at least 12 characters.');

    $user = User::firstOrNew(['email' => $email]);
    $user->fill(['name' => $name, 'password' => $pass]);
    $user->forceFill(['is_platform_admin' => true, 'status' => 'active', 'email_verified_at' => now()])->save();

    $this->info("Platform administrator {$email} is ready. Enable two-factor authentication after signing in.");
})->purpose('Create or promote a platform super administrator');

Artisan::command('power:import-legacy-tokens', function () {
    $moved = 0;
    DB::table('legacy_token_imports')->orderBy('id')->limit(1000)->get()->each(function (object $row) use (&$moved) {
        DB::transaction(function () use ($row, &$moved) {
            $purchase = PowerPurchase::where('reference', $row->purchase_reference)->first();

            if ($purchase && ! $purchase->token()->exists()) {
                PowerToken::create([
                    'power_purchase_id' => $purchase->id,
                    'token' => preg_replace('/\s+/', '', $row->token),
                    'units' => $row->units,
                    'meter_number' => $row->meter_number,
                    'source' => 'legacy',
                    'meta' => $row->meta ? json_decode($row->meta, true) : null,
                    'issued_at' => $row->issued_at,
                ]);
                $moved++;
            }

            DB::table('legacy_token_imports')->where('id', $row->id)->delete();
        });
    });
    $this->info("Imported {$moved} legacy tokens");
})->purpose('Encrypt electricity tokens imported from the legacy PowerNest app into power_tokens');

Schedule::command('power:reconcile')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('power:import-legacy-tokens')->everyMinute()->withoutOverlapping();
Schedule::command('passes:expire')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('billing:remind')->dailyAt('08:00')->timezone('Africa/Lagos')->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('model:prune')->daily();
