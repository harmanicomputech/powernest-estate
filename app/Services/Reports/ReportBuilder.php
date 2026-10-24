<?php

namespace App\Services\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PowerPurchaseStatus;
use App\Models\Estate;
use App\Models\Expense;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PowerPurchase;
use App\Models\Visit;
use App\Services\Billing\LevyLockout;
use App\Support\Money;
use BackedEnum;
use Illuminate\Support\Carbon;

/**
 * Builds downloadable reports (CSV / PDF) for a date range. Money columns are formatted in naira.
 *
 * @phpstan-type Report array{title: string, columns: list<string>, rows: list<list<string|int|float|null>>, summary: array<string, string>}
 */
class ReportBuilder
{
    public const TYPES = [
        'power' => 'Electricity sales (every token)',
        'power_meters' => 'Electricity by meter',
        'levy' => 'Yearly levy status',
        'payments' => 'Payments received',
        'expenses' => 'Expenses',
        'visitors' => 'Visitor log',
        'maintenance' => 'Maintenance requests',
        'incidents' => 'Incidents',
    ];

    public function __construct(private LevyLockout $lockout) {}

    /**
     * @return Report
     */
    public function build(string $type, Estate $estate, Carbon $from, Carbon $to): array
    {
        $tz = $estate->timezone();
        $range = [$from->copy()->startOfDay()->utc(), $to->copy()->endOfDay()->utc()];

        $report = match ($type) {
            'power' => $this->power($estate, $range, $tz),
            'power_meters' => $this->powerByMeter($estate, $range),
            'levy' => $this->levy($estate, $from, $to),
            'payments' => $this->payments($estate, $range, $tz),
            'expenses' => $this->expenses($estate, $from, $to),
            'visitors' => $this->visitors($estate, $range, $tz),
            'maintenance' => $this->maintenance($estate, $range, $tz),
            'incidents' => $this->incidents($estate, $range, $tz),
        };

        return $report + ['title' => self::TYPES[$type]];
    }

    private function power(Estate $estate, array $range, string $tz): array
    {
        $purchases = PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)
            ->whereBetween('token_issued_at', $range)->with('user', 'property', 'token', 'issuer')->orderBy('token_issued_at')->get();

        $rows = $purchases->map(fn (PowerPurchase $p) => [
            $p->token_issued_at->timezone($tz)->format('Y-m-d H:i'), $p->reference, $p->user?->name, $p->property?->identifier, $p->meter_number,
            $p->isOfficeVend() ? 'Estate office' : ucfirst($p->gateway), $this->naira($p->vend_amount), $this->naira($p->service_charge),
            $this->naira($p->amount_paid ?? 0), $p->token?->units, $p->issuer?->name,
        ])->all();

        $office = $purchases->filter->isOfficeVend();
        $summary = [
            'Tokens issued' => (string) $purchases->count(),
            'Electricity value' => Money::format((int) $purchases->sum('vend_amount')),
            'Units (kWh)' => number_format((float) $purchases->sum(fn ($p) => (float) $p->token?->units), 2),
            'Service charges' => Money::format((int) $purchases->sum('service_charge')),
            'Money collected' => Money::format((int) $purchases->sum('amount_paid')),
            'Issued by office (no payment)' => $office->count().' · '.Money::format((int) $office->sum('vend_amount')),
        ];
        foreach ($purchases->groupBy('gateway') as $gateway => $group) {
            $summary['Via '.($gateway === 'office' ? 'estate office' : ucfirst($gateway))] = $group->count().' · '.Money::format((int) $group->sum('vend_amount'));
        }

        return [
            'columns' => ['Date', 'Reference', 'Customer', 'Property', 'Meter', 'Paid via', 'Value (₦)', 'Service charge (₦)', 'Collected (₦)', 'Units', 'Issued by'],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    private function powerByMeter(Estate $estate, array $range): array
    {
        $purchases = PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)
            ->whereBetween('token_issued_at', $range)->with('property', 'token')->get();

        $rows = $purchases->groupBy('meter_number')->map(fn ($group, $meter) => [
            $meter, $group->first()->property?->identifier, $group->count(), $this->naira((int) $group->sum('vend_amount')),
            number_format((float) $group->sum(fn ($p) => (float) $p->token?->units), 2, '.', ''), $this->naira((int) $group->sum('amount_paid')),
        ])->sortByDesc(fn ($row) => (float) $row[3])->values()->all();

        return [
            'columns' => ['Meter', 'Property', 'Tokens', 'Value (₦)', 'Units (kWh)', 'Collected (₦)'],
            'rows' => $rows,
            'summary' => ['Meters' => (string) count($rows), 'Tokens' => (string) $purchases->count(), 'Electricity value' => Money::format((int) $purchases->sum('vend_amount'))],
        ];
    }

    private function levy(Estate $estate, Carbon $from, Carbon $to): array
    {
        $invoices = Invoice::forEstate($estate)->where('status', '!=', InvoiceStatus::Void->value)
            ->whereHas('chargeType', fn ($q) => $q->where('frequency', 'annual'))
            ->whereDate('period_start', '<=', $to->toDateString())->whereDate('period_end', '>=', $from->toDateString())
            ->with('property', 'billedUser', 'chargeType')->orderBy('property_id')->get();

        $rows = $invoices->map(fn (Invoice $i) => [
            $i->property?->identifier, $i->billedUser?->name, $i->billedUser?->phone, $i->number, $i->period_start?->format('Y'),
            $this->naira($i->total), $this->naira($i->amount_paid), $this->naira($i->balance()), $i->status->label(),
            $i->due_date?->format('Y-m-d'), $i->status === InvoiceStatus::Paid ? '' : ($this->lockout->locksOn($i, $estate)?->isPast() ? 'Locked out' : ''),
        ])->all();

        $paid = $invoices->where('status', InvoiceStatus::Paid);

        return [
            'columns' => ['Property', 'Billed to', 'Phone', 'Invoice', 'Year', 'Amount (₦)', 'Paid (₦)', 'Balance (₦)', 'Status', 'Due date', 'Access'],
            'rows' => $rows,
            'summary' => [
                'Homes billed' => (string) $invoices->count(),
                'Fully paid' => $paid->count().' homes',
                'Not fully paid' => ($invoices->count() - $paid->count()).' homes',
                'Billed' => Money::format((int) $invoices->sum('total')),
                'Collected' => Money::format((int) $invoices->sum('amount_paid')),
                'Outstanding' => Money::format((int) $invoices->sum(fn (Invoice $i) => $i->balance())),
            ],
        ];
    }

    private function payments(Estate $estate, array $range, string $tz): array
    {
        $payments = Payment::forEstate($estate)->where('status', PaymentStatus::Succeeded->value)->whereBetween('paid_at', $range)
            ->with('payer', 'property', 'invoice')->orderBy('paid_at')->get();

        return [
            'columns' => ['Date', 'Reference', 'Payer', 'Property', 'For', 'Method', 'Invoice', 'Amount (₦)'],
            'rows' => $payments->map(fn (Payment $p) => [
                $p->paid_at->timezone($tz)->format('Y-m-d H:i'), $p->reference, $p->payer?->name, $p->property?->identifier, humanize($p->purpose),
                humanize($p->method), $p->invoice?->number, $this->naira($p->amount_received ?? $p->amount),
            ])->all(),
            'summary' => ['Payments' => (string) $payments->count(), 'Total received' => Money::format((int) $payments->sum(fn ($p) => $p->amount_received ?? $p->amount))]
                + $payments->groupBy('method')->map(fn ($g) => $g->count().' · '.Money::format((int) $g->sum(fn ($p) => $p->amount_received ?? $p->amount)))->mapWithKeys(fn ($v, $k) => [humanize($k) => $v])->all(),
        ];
    }

    private function expenses(Estate $estate, Carbon $from, Carbon $to): array
    {
        $expenses = Expense::forEstate($estate)->whereBetween('incurred_on', [$from->toDateString(), $to->toDateString()])->with('vendor', 'submitter')->orderBy('incurred_on')->get();
        $approved = $expenses->filter(fn (Expense $e) => in_array($e->status, [ExpenseStatus::Approved, ExpenseStatus::Paid], true));

        return [
            'columns' => ['Date', 'Category', 'Description', 'Vendor', 'Status', 'Submitted by', 'Amount (₦)'],
            'rows' => $expenses->map(fn (Expense $e) => [$e->incurred_on?->format('Y-m-d'), humanize($e->category), $e->description, $e->vendor?->name, $this->label($e->status), $e->submitter?->name, $this->naira($e->amount)])->all(),
            'summary' => ['Expenses recorded' => (string) $expenses->count(), 'Approved / paid' => Money::format((int) $approved->sum('amount'))],
        ];
    }

    private function visitors(Estate $estate, array $range, string $tz): array
    {
        $visits = Visit::forEstate($estate)->whereBetween('created_at', $range)->with('property', 'host')->orderBy('created_at')->get();

        return [
            'columns' => ['Date', 'Visitor', 'Category', 'Visiting', 'Host', 'Status', 'Checked in', 'Checked out', 'Vehicle'],
            'rows' => $visits->map(fn (Visit $v) => [
                $v->created_at->timezone($tz)->format('Y-m-d'), $v->visitor_name, humanize($v->category), $v->property?->identifier, $v->host?->name, $this->label($v->status),
                $v->checked_in_at?->timezone($tz)->format('H:i'), $v->checked_out_at?->timezone($tz)->format('H:i'), $v->vehicle_plate,
            ])->all(),
            'summary' => ['Visits' => (string) $visits->count(), 'Checked in' => (string) $visits->whereNotNull('checked_in_at')->count()],
        ];
    }

    private function maintenance(Estate $estate, array $range, string $tz): array
    {
        $requests = MaintenanceRequest::forEstate($estate)->whereBetween('created_at', $range)->with('property', 'requester', 'assignee')->orderBy('created_at')->get();

        return [
            'columns' => ['Opened', 'Reference', 'Title', 'Category', 'Property', 'Requested by', 'Assigned to', 'Priority', 'Status', 'Completed', 'Cost (₦)'],
            'rows' => $requests->map(fn (MaintenanceRequest $m) => [
                $m->created_at->timezone($tz)->format('Y-m-d'), $m->reference, $m->title, humanize($m->category), $m->property?->identifier, $m->requester?->name,
                $m->assignee?->name, $this->label($m->priority), $this->label($m->status), $m->completed_at?->timezone($tz)->format('Y-m-d'),
                $this->naira((int) $m->labour_cost + (int) $m->parts_cost),
            ])->all(),
            'summary' => ['Requests' => (string) $requests->count(), 'Completed' => (string) $requests->whereNotNull('completed_at')->count()],
        ];
    }

    private function incidents(Estate $estate, array $range, string $tz): array
    {
        $incidents = Incident::forEstate($estate)->whereBetween('occurred_at', $range)->with('property', 'reporter')->orderBy('occurred_at')->get();

        return [
            'columns' => ['Date', 'Reference', 'Title', 'Category', 'Severity', 'Status', 'Location', 'Reported by'],
            'rows' => $incidents->map(fn (Incident $i) => [
                $i->occurred_at?->timezone($tz)->format('Y-m-d H:i'), $i->reference, $i->title, humanize($i->category), $this->label($i->severity), $this->label($i->status),
                $i->location ?? $i->property?->identifier, $i->reporter?->name,
            ])->all(),
            'summary' => ['Incidents' => (string) $incidents->count()],
        ];
    }

    private function naira(?int $kobo): string
    {
        return Money::toDecimalString((int) $kobo);
    }

    private function label(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return method_exists($value, 'label') ? $value->label() : humanize((string) $value->value);
        }

        return $value === null ? null : humanize((string) $value);
    }
}
