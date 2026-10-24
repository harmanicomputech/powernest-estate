<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Decides whether a resident is locked out of the app for an unpaid yearly levy.
 * A yearly-charge invoice on any home the user occupies that is still unpaid
 * more than `levy.grace_days` after its due date locks every occupant of that home.
 */
class LevyLockout
{
    /** @var array<string, Collection<int, Invoice>> */
    private array $resolved = [];

    /**
     * @return Collection<int, Invoice>
     */
    public function blockingInvoices(User $user, Estate $estate): Collection
    {
        if (! $estate->setting('levy.lockout_enabled')) {
            return collect();
        }

        return $this->resolved[$user->id.':'.$estate->id] ??= Invoice::forEstate($estate)
            ->whereIn('property_id', $user->authorizedProperties($estate)->pluck('properties.id'))
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->whereHas('chargeType', fn ($query) => $query->where('frequency', 'annual'))
            ->whereDate('due_date', '<', now($estate->timezone())->subDays($this->graceDays($estate))->toDateString())
            ->with('chargeType', 'property', 'submissions')
            ->orderBy('due_date')
            ->get();
    }

    public function isLocked(User $user, Estate $estate): bool
    {
        return $this->blockingInvoices($user, $estate)->isNotEmpty();
    }

    /**
     * First day the resident is locked out if the invoice is still unpaid.
     */
    public function locksOn(Invoice $invoice, Estate $estate): ?Carbon
    {
        if (! $estate->setting('levy.lockout_enabled') || ! $invoice->due_date) {
            return null;
        }

        return $invoice->due_date->copy()->addDays($this->graceDays($estate) + 1);
    }

    private function graceDays(Estate $estate): int
    {
        return max(0, (int) $estate->setting('levy.grace_days'));
    }
}
