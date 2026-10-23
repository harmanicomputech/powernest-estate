<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\OccupancyRelationship;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\LedgerEntry;
use App\Models\Occupancy;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Notifications\PaymentReceipt;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Reference;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Estate charges and receivables. Every money movement writes an append-only ledger entry;
 * nothing is edited in place to correct history.
 */
class BillingService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  list<array{description: string, quantity: int, unit_amount: int}>  $items
     */
    public function issueInvoice(Estate $estate, Property $property, array $items, string $description, Carbon $dueDate, User $actor, ?ChargeType $chargeType = null, ?Carbon $periodStart = null, ?Carbon $periodEnd = null, bool $notify = true): Invoice
    {
        abort_unless($property->estate_id === $estate->id, 404);

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one line item.']);
        }

        $invoice = DB::transaction(function () use ($estate, $property, $items, $description, $dueDate, $actor, $chargeType, $periodStart, $periodEnd) {
            // Serialise numbering per estate.
            Estate::whereKey($estate->id)->lockForUpdate()->first();

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['quantity'] * $item['unit_amount'];
            }

            $invoice = Invoice::create([
                'estate_id' => $estate->id,
                'property_id' => $property->id,
                'billed_user_id' => $this->billableOccupant($property)?->id,
                'charge_type_id' => $chargeType?->id,
                'number' => $this->nextNumber($estate),
                'description' => $description,
                'issue_date' => now()->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'period_start' => $periodStart?->toDateString(),
                'period_end' => $periodEnd?->toDateString(),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'status' => InvoiceStatus::Issued,
                'created_by' => $actor->id,
            ]);

            foreach ($items as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_amount' => $item['unit_amount'],
                    'amount' => $item['quantity'] * $item['unit_amount'],
                ]);
            }

            $this->ledger($invoice, 'invoice_issued', debit: $subtotal, description: "Invoice {$invoice->number}: {$description}", actor: $actor);
            $this->audit->record('invoice.issued', $invoice, ['total' => $subtotal, 'property' => $property->identifier], $estate, $actor);

            return $invoice;
        });

        if ($notify) {
            $invoice->billedUser?->notify(new InvoiceIssued($invoice));
        }

        return $invoice;
    }

    /**
     * Issue a recurring charge for a period to every eligible property. Re-running is safe:
     * the unique (property, charge type, period) index prevents duplicates.
     *
     * @return array{issued: int, skipped: int}
     */
    public function runCharge(ChargeType $chargeType, Carbon $periodStart, Carbon $periodEnd, Carbon $dueDate, User $actor): array
    {
        $estate = $chargeType->estate;
        $stats = ['issued' => 0, 'skipped' => 0];

        $properties = Property::where('estate_id', $estate->id)
            ->when($chargeType->property_types, fn ($q, $types) => $q->whereIn('type', $types))
            ->whereHas('occupancies', fn ($q) => $q->where('status', 'active'))
            ->get();

        foreach ($properties as $property) {
            $exists = Invoice::where('property_id', $property->id)->where('charge_type_id', $chargeType->id)->whereDate('period_start', $periodStart)->exists();

            if ($exists) {
                $stats['skipped']++;

                continue;
            }

            try {
                $this->issueInvoice($estate, $property, [[
                    'description' => $chargeType->name.' · '.$periodStart->format('M Y'),
                    'quantity' => 1,
                    'unit_amount' => $chargeType->amount,
                ]], $chargeType->name.' for '.$periodStart->format('F Y'), $dueDate, $actor, $chargeType, $periodStart, $periodEnd);
                $stats['issued']++;
            } catch (QueryException) {
                $stats['skipped']++;
            }
        }

        $this->audit->record('billing.charge_run', $chargeType, $stats + ['period' => $periodStart->toDateString()], $estate, $actor);

        return $stats;
    }

    public function requestAdjustment(Invoice $invoice, string $type, int $amount, string $reason, User $actor): InvoiceAdjustment
    {
        if (! $invoice->isPayable() || $amount < 1 || $amount > $invoice->balance()) {
            throw ValidationException::withMessages(['amount' => 'Adjustments must be between ₦0.01 and the outstanding balance of an open invoice.']);
        }

        $adjustment = $invoice->adjustments()->create(['type' => $type, 'amount' => $amount, 'reason' => $reason, 'requested_by' => $actor->id]);
        $this->audit->record('invoice.adjustment_requested', $invoice, ['type' => $type, 'amount' => $amount], $invoice->estate_id, $actor);

        return $adjustment;
    }

    /**
     * Four-eyes rule: an approver cannot approve their own request.
     */
    public function reviewAdjustment(InvoiceAdjustment $adjustment, User $reviewer, bool $approve): void
    {
        $invoice = $adjustment->invoice;
        abort_unless($reviewer->hasEstatePermission($invoice->estate, Permission::BillingApprove), 403);

        if ($adjustment->requested_by === $reviewer->id) {
            throw ValidationException::withMessages(['adjustment' => 'Another approver must review your own adjustment request.']);
        }

        DB::transaction(function () use ($adjustment, $reviewer, $approve) {
            $locked = InvoiceAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'Already reviewed.');

            $locked->update(['status' => $approve ? 'approved' : 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);

            if ($approve) {
                $invoice = Invoice::whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();
                $amount = min($locked->amount, $invoice->balance());
                $invoice->adjustment_total += $amount;
                $invoice->total = $invoice->subtotal - $invoice->adjustment_total;
                $invoice->status = $this->statusFor($invoice);
                $invoice->save();
                $this->ledger($invoice, 'adjustment', credit: $amount, description: ucfirst($locked->type)." on {$invoice->number}: {$locked->reason}", actor: $reviewer, source: $locked);
            }

            $this->audit->record($approve ? 'invoice.adjustment_approved' : 'invoice.adjustment_rejected', $locked, [], $locked->invoice->estate_id, $reviewer);
        });
    }

    public function void(Invoice $invoice, User $actor, string $reason): void
    {
        DB::transaction(function () use ($invoice, $actor, $reason) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->amount_paid > 0 || $locked->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages(['invoice' => 'Only unpaid invoices can be voided. Record a refund or adjustment instead.']);
            }

            $locked->update(['status' => InvoiceStatus::Void, 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason]);
            $this->ledger($locked, 'invoice_voided', credit: $locked->total, description: "Void {$locked->number}: {$reason}", actor: $actor);
            $this->audit->record('invoice.voided', $locked, ['reason' => $reason], $locked->estate_id, $actor);
        });
    }

    /**
     * Records an offline payment (bank transfer, cash, POS) with its supporting reference.
     */
    public function recordManualPayment(Invoice $invoice, int $amount, string $method, string $externalReference, Carbon $paidAt, User $actor, ?string $notes = null): Payment
    {
        $payment = DB::transaction(function () use ($invoice, $amount, $method, $externalReference, $paidAt, $actor, $notes) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPayable() || $amount < 1 || $amount > $locked->balance()) {
                throw ValidationException::withMessages(['amount' => 'Amount must not exceed the outstanding balance of '.Money::format($locked->balance()).'.']);
            }

            if (Payment::where('estate_id', $locked->estate_id)->where('method', $method)->where('external_reference', $externalReference)->where('status', PaymentStatus::Succeeded->value)->exists()) {
                throw ValidationException::withMessages(['external_reference' => 'A payment with this reference has already been recorded.']);
            }

            $payment = Payment::create([
                'estate_id' => $locked->estate_id,
                'property_id' => $locked->property_id,
                'payer_id' => $locked->billed_user_id,
                'invoice_id' => $locked->id,
                'reference' => $this->uniquePaymentReference(),
                'purpose' => 'estate_invoice',
                'method' => $method,
                'amount' => $amount,
                'amount_received' => $amount,
                'status' => PaymentStatus::Succeeded,
                'external_reference' => $externalReference,
                'paid_at' => $paidAt,
                'recorded_by' => $actor->id,
                'notes' => $notes,
            ]);

            $this->allocate($payment, $locked, $amount, $actor);
            $this->audit->record('payment.recorded_manually', $payment, ['invoice' => $locked->number, 'amount' => $amount, 'method' => $method], $locked->estate_id, $actor);

            return $payment;
        });

        $payment->payer?->notify(new PaymentReceipt($payment));

        return $payment;
    }

    public function allocate(Payment $payment, Invoice $lockedInvoice, int $amount, ?User $actor): void
    {
        PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $lockedInvoice->id, 'amount' => $amount]);

        $lockedInvoice->amount_paid += $amount;
        $lockedInvoice->status = $this->statusFor($lockedInvoice);
        $lockedInvoice->save();

        $this->ledger($lockedInvoice, 'payment_received', credit: $amount, description: "Payment {$payment->reference} on {$lockedInvoice->number}", actor: $actor, source: $payment);
    }

    public function uniquePaymentReference(): string
    {
        do {
            $reference = Reference::make('PNB', 12);
        } while (Payment::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Outstanding balance for a property, derived from the ledger.
     */
    public function propertyBalance(Property $property): int
    {
        $row = LedgerEntry::where('property_id', $property->id)->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();

        return (int) $row->d - (int) $row->c;
    }

    private function statusFor(Invoice $invoice): InvoiceStatus
    {
        return match (true) {
            $invoice->status === InvoiceStatus::Void => InvoiceStatus::Void,
            $invoice->amount_paid >= $invoice->total => InvoiceStatus::Paid,
            $invoice->amount_paid > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Issued,
        };
    }

    private function billableOccupant(Property $property): ?User
    {
        $occupancies = Occupancy::with('user')->where('property_id', $property->id)->where('status', 'active')->where('is_primary', true)->get();

        return ($occupancies->firstWhere('relationship', OccupancyRelationship::Tenant)
            ?? $occupancies->firstWhere('relationship', OccupancyRelationship::Owner)
            ?? $occupancies->firstWhere('relationship', OccupancyRelationship::Landlord))?->user;
    }

    private function nextNumber(Estate $estate): string
    {
        $prefix = $estate->setting('billing.invoice_prefix').'-'.now()->format('y');
        $last = Invoice::where('estate_id', $estate->id)->where('number', 'like', $prefix.'-%')->orderByDesc('id')->value('number');
        $sequence = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function ledger(Invoice $invoice, string $type, int $debit = 0, int $credit = 0, string $description = '', ?User $actor = null, $source = null): void
    {
        $source ??= $invoice;

        LedgerEntry::create([
            'estate_id' => $invoice->estate_id,
            'property_id' => $invoice->property_id,
            'entry_type' => $type,
            'debit' => $debit,
            'credit' => $credit,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'description' => mb_substr($description, 0, 250),
            'created_by' => $actor?->id,
        ]);
    }
}
