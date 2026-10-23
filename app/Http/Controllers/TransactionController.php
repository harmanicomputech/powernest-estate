<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\PowerPurchaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\PowerPurchase;
use App\Models\User;
use App\Support\EstateContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * One place for a resident's money history: estate payments, receipts awaiting verification,
 * electricity purchases, and every invoice for their properties, each downloadable as PDF.
 */
class TransactionController extends Controller
{
    private const PER_SOURCE = 300;

    public function __construct(private EstateContext $context) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $estate = $this->context->estate();
        $tab = in_array($request->query('tab'), ['estate', 'electricity', 'invoices'], true) ? $request->query('tab') : 'all';
        $propertyIds = $user->authorizedProperties($estate)->pluck('properties.id');
        $canBills = Gate::allows('billing.view_own');

        if ($tab === 'invoices') {
            $invoices = $canBills
                ? Invoice::forEstate($estate)->whereIn('property_id', $propertyIds)->with('property')->latest('issue_date')->latest('id')->paginate(20)->withQueryString()
                : null;

            return view('transactions.index', compact('tab', 'invoices') + ['items' => null, 'totals' => $this->totals($user, $estate, $propertyIds)]);
        }

        $items = collect();

        if ($tab !== 'electricity' && $canBills) {
            $items = $items->merge($this->estatePayments($estate->id, $user, $propertyIds))->merge($this->submissions($estate->id, $user));
        }

        if ($tab !== 'estate' && Gate::allows('power.purchase')) {
            $items = $items->merge($this->purchases($estate->id, $user));
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $sorted = $items->sortByDesc('date')->values();
        $paginator = new LengthAwarePaginator($sorted->forPage($page, 20)->values(), $sorted->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('transactions.index', ['tab' => $tab, 'items' => $paginator, 'invoices' => null, 'totals' => $this->totals($user, $estate, $propertyIds)]);
    }

    /**
     * @param  Collection<int, int>  $propertyIds
     * @return Collection<int, array<string, mixed>>
     */
    private function estatePayments(int $estateId, User $user, Collection $propertyIds): Collection
    {
        return Payment::where('estate_id', $estateId)
            ->whereIn('status', [PaymentStatus::Succeeded->value, PaymentStatus::Reversed->value])
            ->where(fn ($q) => $q->whereIn('property_id', $propertyIds)->orWhere('payer_id', $user->id))
            ->with('invoice')->latest('paid_at')->limit(self::PER_SOURCE)->get()
            ->map(fn (Payment $p) => [
                'date' => $p->paid_at,
                'kind' => 'estate',
                'icon' => 'receipt',
                'title' => $p->invoice?->description ?? 'Estate payment',
                'subtitle' => $p->reference.' · '.humanize($p->method),
                'amount' => $p->amount_received ?? $p->amount,
                'status' => $p->status->label(),
                'tone' => $p->status->tone(),
                'url' => route('bills.receipt', $p),
                'pdf' => route('pdf.payment', $p),
                'pdfLabel' => 'Receipt',
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function submissions(int $estateId, User $user): Collection
    {
        return PaymentSubmission::where('estate_id', $estateId)->where('submitted_by', $user->id)
            ->whereIn('status', [SubmissionStatus::Pending->value, SubmissionStatus::Rejected->value])
            ->with('invoice')->latest()->limit(self::PER_SOURCE)->get()
            ->map(fn (PaymentSubmission $s) => [
                'date' => $s->created_at,
                'kind' => 'estate',
                'icon' => 'file-check',
                'title' => ($s->invoice?->description ?? 'Offline payment').' (receipt uploaded)',
                'subtitle' => $s->reference.' · '.(PaymentSubmission::METHODS[$s->method] ?? $s->method).($s->status === SubmissionStatus::Rejected && $s->review_note ? ' · '.$s->review_note : ''),
                'amount' => $s->amount,
                'status' => $s->status->label(),
                'tone' => $s->status->tone(),
                'url' => route('bills.show', $s->invoice),
                'pdf' => null,
                'pdfLabel' => null,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function purchases(int $estateId, User $user): Collection
    {
        return PowerPurchase::where('estate_id', $estateId)->where('user_id', $user->id)
            ->where('status', '!=', PowerPurchaseStatus::Expired->value)
            ->latest()->limit(self::PER_SOURCE)->get()
            ->map(fn (PowerPurchase $p) => [
                'date' => $p->paid_at ?? $p->created_at,
                'kind' => 'electricity',
                'icon' => 'zap',
                'title' => 'Electricity · meter '.$p->meter_number,
                'subtitle' => $p->reference.' · '.ucfirst($p->gateway),
                'amount' => $p->amount_paid ?? $p->total_amount,
                'status' => $p->status->label(),
                'tone' => $p->status->tone(),
                'url' => route('power.purchases.show', $p),
                'pdf' => $p->status->isPaymentConfirmed() ? route('pdf.purchase', $p) : null,
                'pdfLabel' => 'Receipt',
            ]);
    }

    /**
     * @param  Collection<int, int>  $propertyIds
     * @return array{paidThisYear: int, electricityThisYear: int, outstanding: int}
     */
    private function totals(User $user, $estate, Collection $propertyIds): array
    {
        $yearStart = now($estate->timezone())->startOfYear()->utc();

        return [
            'paidThisYear' => (int) Payment::forEstate($estate)->where('status', PaymentStatus::Succeeded->value)->whereIn('property_id', $propertyIds)->where('paid_at', '>=', $yearStart)->sum('amount_received'),
            'electricityThisYear' => (int) PowerPurchase::forEstate($estate)->where('user_id', $user->id)->where('status', PowerPurchaseStatus::TokenIssued->value)->where('paid_at', '>=', $yearStart)->sum('amount_paid'),
            'outstanding' => (int) Invoice::forEstate($estate)->whereIn('property_id', $propertyIds)->outstanding()->selectRaw('COALESCE(SUM(total - amount_paid), 0) as b')->value('b'),
        ];
    }
}
