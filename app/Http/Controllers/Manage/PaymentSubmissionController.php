<?php

namespace App\Http\Controllers\Manage;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentSubmission;
use App\Services\Billing\PaymentSubmissionService;
use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PaymentSubmissionController extends Controller
{
    public function __construct(private EstateContext $context, private PaymentSubmissionService $service) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();
        $status = SubmissionStatus::tryFrom((string) $request->query('status')) ?? SubmissionStatus::Pending;

        return view('manage.billing.submissions.index', [
            'submissions' => PaymentSubmission::forEstate($estate)->with('invoice', 'property', 'submitter', 'reviewer')
                ->where('status', $status->value)
                ->when($status === SubmissionStatus::Pending, fn ($q) => $q->oldest(), fn ($q) => $q->latest('reviewed_at'))
                ->paginate(25)->withQueryString(),
            'status' => $status,
            'counts' => PaymentSubmission::forEstate($estate)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(PaymentSubmission $submission): View
    {
        return view('manage.billing.submissions.show', [
            'submission' => $submission->load('invoice.items', 'invoice.allocations.payment', 'property', 'submitter', 'reviewer', 'receipt', 'payment'),
            'history' => PaymentSubmission::where('invoice_id', $submission->invoice_id)->whereKeyNot($submission->id)->with('reviewer')->latest()->get(),
        ]);
    }

    public function approve(PaymentSubmission $submission, Request $request): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'string', 'max:15'], 'note' => ['nullable', 'string', 'max:250']]);

        try {
            $amount = Money::parse($data['amount']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid amount.']);
        }

        $this->service->approve($submission, $request->user(), $amount, $data['note'] ?? null);

        return redirect()->route('manage.billing.submissions.index')->with('success', "Approved {$submission->reference}. The payment has been applied to {$submission->invoice->number}.");
    }

    public function reject(PaymentSubmission $submission, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $this->service->reject($submission, $request->user(), $data['reason']);

        return redirect()->route('manage.billing.submissions.index')->with('success', "Rejected {$submission->reference}. The resident has been told why.");
    }
}
