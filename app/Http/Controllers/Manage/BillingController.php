<?php

namespace App\Http\Controllers\Manage;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ChargeType;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\Property;
use App\Notifications\InvoiceReminder;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingService;
use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class BillingController extends Controller
{
    public function __construct(private EstateContext $context, private BillingService $billing) {}

    public function index(): View
    {
        $estate = $this->context->estate();
        $monthStart = now($estate->timezone())->startOfMonth()->utc();

        return view('manage.billing.index', [
            'outstanding' => (int) Invoice::forEstate($estate)->outstanding()->selectRaw('COALESCE(SUM(total - amount_paid),0) as b')->value('b'),
            'overdue' => (int) Invoice::forEstate($estate)->outstanding()->where('due_date', '<', now()->toDateString())->selectRaw('COALESCE(SUM(total - amount_paid),0) as b')->value('b'),
            'overdueCount' => Invoice::forEstate($estate)->outstanding()->where('due_date', '<', now()->toDateString())->count(),
            'collected' => (int) Payment::forEstate($estate)->where('purpose', 'estate_invoice')->where('status', PaymentStatus::Succeeded->value)->where('paid_at', '>=', $monthStart)->sum('amount_received'),
            'chargeTypes' => ChargeType::forEstate($estate)->withCount('invoices')->orderBy('name')->get(),
            'adjustments' => InvoiceAdjustment::whereHas('invoice', fn ($q) => $q->where('estate_id', $estate->id))->where('status', 'pending')->with('invoice.property', 'requester')->get(),
            'recentPayments' => Payment::forEstate($estate)->where('status', PaymentStatus::Succeeded->value)->with('invoice', 'property')->latest('paid_at')->limit(8)->get(),
            'pendingReceipts' => PaymentSubmission::forEstate($estate)->where('status', 'pending')->count(),
            'needsReview' => Payment::forEstate($estate)->where('status', PaymentStatus::Pending->value)->whereNotNull('notes')->with('invoice')->get(),
        ]);
    }

    public function invoices(Request $request): View
    {
        $estate = $this->context->estate();
        $status = $request->query('status', 'outstanding');
        $search = trim((string) $request->query('q'));

        $invoices = Invoice::forEstate($estate)->with('property', 'billedUser')
            ->when($status === 'outstanding', fn ($q) => $q->outstanding())
            ->when($status === 'overdue', fn ($q) => $q->outstanding()->where('due_date', '<', now()->toDateString()))
            ->when($status === 'paid', fn ($q) => $q->where('status', InvoiceStatus::Paid->value))
            ->when($status === 'void', fn ($q) => $q->where('status', InvoiceStatus::Void->value))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$search}%")->orWhereHas('property', fn ($p) => $p->where('identifier', 'like', "%{$search}%"))))
            ->latest('issue_date')->latest('id')->paginate(30)->withQueryString();

        return view('manage.billing.invoices', compact('invoices', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $estate = $this->context->estate();

        return view('manage.billing.create', [
            'properties' => Property::forEstate($estate)->orderBy('identifier')->get(['id', 'identifier']),
            'selected' => $request->query('property'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:160'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.description' => ['required', 'string', 'max:160'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.unit_amount' => ['required', 'string', 'max:15'],
        ]);

        $items = [];
        foreach ($data['items'] as $i => $item) {
            try {
                $items[] = ['description' => $item['description'], 'quantity' => (int) $item['quantity'], 'unit_amount' => Money::parse($item['unit_amount'])];
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(["items.$i.unit_amount" => 'Enter a valid amount.']);
            }
        }

        $property = Property::forEstate($estate)->findOrFail($data['property_id']);
        $invoice = $this->billing->issueInvoice($estate, $property, $items, $data['description'], Carbon::parse($data['due_date']), $request->user());

        return redirect()->route('bills.show', $invoice)->with('success', "Invoice {$invoice->number} issued.");
    }

    public function recordPayment(Invoice $invoice, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:15'],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'pos', 'cheque'])],
            'external_reference' => ['required', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = $this->billing->recordManualPayment($invoice, Money::parse($data['amount']), $data['method'], $data['external_reference'], Carbon::parse($data['paid_at']), $request->user(), $data['notes'] ?? null);

        return back()->with('success', "Payment {$payment->reference} recorded.");
    }

    public function reversePayment(Payment $payment, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $this->billing->reversePayment($payment, $request->user(), $data['reason']);

        return back()->with('success', "Payment {$payment->reference} reversed. The invoice is open again.");
    }

    public function requestAdjustment(Invoice $invoice, Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['discount', 'waiver'])], 'amount' => ['required', 'string', 'max:15'], 'reason' => ['required', 'string', 'max:250']]);
        $this->billing->requestAdjustment($invoice, $data['type'], Money::parse($data['amount']), $data['reason'], $request->user());

        return back()->with('success', 'Adjustment submitted for approval.');
    }

    public function reviewAdjustment(InvoiceAdjustment $adjustment, Request $request): RedirectResponse
    {
        abort_unless($adjustment->invoice->estate_id === $this->context->estate()->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        $this->billing->reviewAdjustment($adjustment, $request->user(), $data['decision'] === 'approve');

        return back()->with('success', 'Adjustment '.($data['decision'] === 'approve' ? 'approved' : 'rejected').'.');
    }

    public function void(Invoice $invoice, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $this->billing->void($invoice, $request->user(), $data['reason']);

        return back()->with('success', "Invoice {$invoice->number} voided.");
    }

    public function remind(Invoice $invoice, AuditLogger $audit): RedirectResponse
    {
        abort_unless($invoice->isPayable() && $invoice->billedUser, 422);
        abort_if($invoice->last_reminded_at?->gt(now()->subDay()), 429, 'A reminder was sent in the last 24 hours.');

        $invoice->billedUser->notify(new InvoiceReminder($invoice));
        $invoice->update(['last_reminded_at' => now()]);
        $audit->record('invoice.reminded', $invoice, [], $invoice->estate_id);

        return back()->with('success', 'Reminder sent.');
    }

    public function storeChargeType(Request $request, AuditLogger $audit): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('charge_types')->where('estate_id', $estate->id)],
            'amount' => ['required', 'string', 'max:15'],
            'frequency' => ['required', Rule::in(['monthly', 'quarterly', 'annual', 'one_time'])],
        ]);

        $chargeType = ChargeType::create(['estate_id' => $estate->id, 'name' => $data['name'], 'code' => strtoupper($data['code']), 'amount' => Money::parse($data['amount']), 'frequency' => $data['frequency']]);
        $audit->record('charge_type.created', $chargeType, ['amount' => $chargeType->amount], $estate);

        return back()->with('success', "{$chargeType->name} added.");
    }

    public function runCharge(ChargeType $chargeType, Request $request): RedirectResponse
    {
        $tz = $this->context->estate()->timezone();
        $data = $request->validate(['period' => ['required', 'date_format:Y-m'], 'due_date' => ['required', 'date', 'after_or_equal:today']]);

        $start = Carbon::createFromFormat('Y-m-d', $data['period'].'-01', $tz)->startOfDay();
        $end = match ($chargeType->frequency) {
            'quarterly' => $start->copy()->addMonths(3)->subDay(),
            'annual' => $start->copy()->addYear()->subDay(),
            default => $start->copy()->endOfMonth(),
        };

        $stats = $this->billing->runCharge($chargeType, $start, $end, Carbon::parse($data['due_date']), $request->user());

        return back()->with('success', "{$stats['issued']} invoices issued, {$stats['skipped']} already billed for this period.");
    }
}
