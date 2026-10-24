<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Services\Billing\LevyLockout;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LevyLockController extends Controller
{
    public function __invoke(Request $request, EstateContext $context, LevyLockout $lockout): View|RedirectResponse
    {
        $invoices = $lockout->blockingInvoices($request->user(), $context->estate());

        if ($invoices->isEmpty()) {
            return redirect()->route('dashboard');
        }

        return view('levy.locked', [
            'estate' => $context->estate(),
            'invoices' => $invoices,
            'underReview' => $invoices->filter(fn ($invoice) => $invoice->submissions->contains(fn ($submission) => $submission->status === SubmissionStatus::Pending)),
        ]);
    }
}
