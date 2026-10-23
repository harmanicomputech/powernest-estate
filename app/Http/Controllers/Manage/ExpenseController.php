<?php

namespace App\Http\Controllers\Manage;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Vendor;
use App\Services\Audit\AuditLogger;
use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public const CATEGORIES = ['security' => 'Security services', 'cleaning' => 'Cleaning & waste', 'utilities' => 'Utilities & diesel', 'repairs' => 'Repairs & maintenance', 'landscaping' => 'Landscaping', 'admin' => 'Administration', 'insurance' => 'Insurance', 'other' => 'Other'];

    public function __construct(private EstateContext $context, private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();
        $status = $request->query('status');

        return view('manage.expenses.index', [
            'expenses' => Expense::forEstate($estate)->with('vendor', 'submitter', 'reviewer')->when($status, fn ($q) => $q->where('status', $status))->latest('incurred_on')->paginate(25)->withQueryString(),
            'vendors' => Vendor::forEstate($estate)->orderBy('name')->get(),
            'status' => $status,
            'monthTotal' => (int) Expense::forEstate($estate)->whereIn('status', [ExpenseStatus::Approved->value, ExpenseStatus::Paid->value])->where('incurred_on', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
            'pendingTotal' => (int) Expense::forEstate($estate)->where('status', ExpenseStatus::PendingApproval->value)->sum('amount'),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'description' => ['required', 'string', 'max:200'],
            'vendor_id' => ['nullable', 'integer'],
            'vendor_invoice_number' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'string', 'max:15'],
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $data['vendor_id'] = ! empty($data['vendor_id']) ? Vendor::forEstate($estate)->findOrFail($data['vendor_id'])->id : null;
        $data['amount'] = Money::parse($data['amount']);

        $expense = Expense::create($data + ['estate_id' => $estate->id, 'status' => ExpenseStatus::PendingApproval, 'submitted_by' => $request->user()->id]);
        $this->audit->record('expense.submitted', $expense, ['amount' => $expense->amount], $estate);

        return back()->with('success', 'Expense submitted for approval.');
    }

    public function review(Expense $expense, Request $request): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:250']]);

        DB::transaction(function () use ($expense, $request, $data) {
            $locked = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ExpenseStatus::PendingApproval) {
                throw ValidationException::withMessages(['expense' => 'Already reviewed.']);
            }
            if ($locked->submitted_by === $request->user()->id) {
                throw ValidationException::withMessages(['expense' => 'Another approver must review an expense you submitted.']);
            }

            $locked->update(['status' => $data['decision'] === 'approve' ? ExpenseStatus::Approved : ExpenseStatus::Rejected, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['note'] ?? null]);
            $this->audit->record('expense.'.$data['decision'].'d', $locked, [], $locked->estate_id);
        });

        return back()->with('success', 'Expense reviewed.');
    }

    public function markPaid(Expense $expense, Request $request): RedirectResponse
    {
        $data = $request->validate(['payment_reference' => ['required', 'string', 'max:100']]);
        abort_unless($expense->status === ExpenseStatus::Approved, 422, 'Only approved expenses can be marked paid.');

        $expense->update(['status' => ExpenseStatus::Paid, 'paid_at' => now(), 'payment_reference' => $data['payment_reference']]);
        $this->audit->record('expense.paid', $expense, ['reference' => $data['payment_reference']], $expense->estate_id);

        return back()->with('success', 'Expense marked as paid.');
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'service_category' => ['nullable', 'string', 'max:40'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
        ]);

        Vendor::create($data + ['estate_id' => $this->context->estate()->id]);

        return back()->with('success', "{$data['name']} added as a vendor.");
    }
}
