<?php

namespace App\Services\Dashboard;

use App\Enums\BookingStatus;
use App\Enums\IncidentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PassStatus;
use App\Enums\PaymentStatus;
use App\Enums\PowerPurchaseStatus;
use App\Enums\SubmissionStatus;
use App\Enums\VisitStatus;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Estate;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\MembershipRequest;
use App\Models\Payment;
use App\Models\PowerPurchase;
use App\Models\Property;
use App\Models\StaffTask;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitorPass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Database-backed dashboard figures. No sample or placeholder numbers.
 */
class DashboardMetrics
{
    public function resident(User $user, Estate $estate): array
    {
        $propertyIds = $user->authorizedProperties($estate)->pluck('properties.id');
        $outstanding = Invoice::forEstate($estate)->whereIn('property_id', $propertyIds)->outstanding()->get();

        return [
            'properties' => $user->authorizedProperties($estate)->with('building')->get(),
            'activePasses' => VisitorPass::forEstate($estate)->where('host_user_id', $user->id)->where('status', PassStatus::Active->value)->where('valid_until', '>=', now())->with('property')->orderBy('valid_from')->limit(4)->get(),
            'awaiting' => Visit::forEstate($estate)->whereIn('property_id', $propertyIds)->where('status', VisitStatus::AwaitingHost->value)->latest()->get(),
            'lastPurchase' => PowerPurchase::forEstate($estate)->where('user_id', $user->id)->with('token')->latest()->first(),
            'outstanding' => $outstanding->sum(fn (Invoice $i) => $i->balance()),
            'nextInvoice' => $outstanding->sortBy('due_date')->first(),
            'openRequests' => MaintenanceRequest::forEstate($estate)->where('requested_by', $user->id)->whereNotIn('status', ['closed', 'cancelled'])->latest()->limit(3)->get(),
            'announcements' => Announcement::forEstate($estate)->whereNotNull('published_at')->where('audience', 'estate')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderByDesc('is_pinned')->latest('published_at')->limit(3)->get(),
            'bookings' => Booking::forEstate($estate)->where('user_id', $user->id)->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Approved->value])->where('ends_at', '>=', now())->with('facility')->orderBy('starts_at')->limit(2)->get(),
            'annualDues' => $this->annualDues($estate, $propertyIds),
            'recentVisits' => Visit::forEstate($estate)->whereIn('property_id', $propertyIds)->whereNotNull('checked_in_at')->latest('checked_in_at')->limit(4)->get(),
        ];
    }

    /**
     * Status of each yearly charge (e.g. estate management & security levy) for the current year.
     *
     * @param  Collection<int, int>  $propertyIds
     * @return list<array{name: string, year: string, property: string, state: string, invoice: Invoice}>
     */
    public function annualDues(Estate $estate, $propertyIds): array
    {
        $today = now($estate->timezone())->toDateString();

        return Invoice::forEstate($estate)
            ->whereIn('property_id', $propertyIds)
            ->where('status', '!=', InvoiceStatus::Void->value)
            ->whereHas('chargeType', fn ($q) => $q->where('frequency', 'annual'))
            ->whereDate('period_start', '<=', $today)
            ->whereDate('period_end', '>=', $today)
            ->with('chargeType', 'property', 'submissions')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'name' => $invoice->chargeType->name,
                'year' => $invoice->period_start->year === $invoice->period_end->year ? (string) $invoice->period_start->year : $invoice->period_start->format('M Y').' – '.$invoice->period_end->format('M Y'),
                'property' => $invoice->property->identifier,
                'state' => match (true) {
                    $invoice->status === InvoiceStatus::Paid => 'paid',
                    $invoice->submissions->contains(fn ($s) => $s->status === SubmissionStatus::Pending) => 'under_review',
                    default => 'outstanding',
                },
                'invoice' => $invoice,
            ])
            ->values()
            ->all();
    }

    public function security(Estate $estate): array
    {
        [$start, $end] = $this->today($estate);

        return [
            'expectedToday' => VisitorPass::forEstate($estate)->where('status', PassStatus::Active->value)->where('valid_from', '<=', $end)->where('valid_until', '>=', now())->count(),
            'inside' => Visit::forEstate($estate)->where('status', VisitStatus::Inside->value)->count(),
            'entriesToday' => Visit::forEstate($estate)->whereBetween('checked_in_at', [$start, $end])->count(),
            'deniedToday' => Visit::forEstate($estate)->where('status', VisitStatus::Denied->value)->whereBetween('created_at', [$start, $end])->count(),
            'awaiting' => Visit::forEstate($estate)->where('status', VisitStatus::AwaitingHost->value)->with('property')->latest()->get(),
            'activeIncidents' => Incident::forEstate($estate)->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::Investigating->value])->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")->limit(5)->get(),
            'recentEntries' => Visit::forEstate($estate)->whereNotNull('checked_in_at')->with('property')->latest('checked_in_at')->limit(6)->get(),
            'hourly' => $this->hourlyEntries($estate, $start, $end),
        ];
    }

    public function facility(Estate $estate, User $user): array
    {
        $open = MaintenanceRequest::forEstate($estate)->whereNotIn('status', [MaintenanceStatus::Closed->value, MaintenanceStatus::Cancelled->value, MaintenanceStatus::Completed->value]);

        return [
            'open' => (clone $open)->count(),
            'urgent' => (clone $open)->whereIn('priority', ['urgent', 'high'])->count(),
            'overdue' => (clone $open)->where('due_at', '<', now())->count(),
            'unassigned' => (clone $open)->where('status', MaintenanceStatus::Submitted->value)->count(),
            'completed30' => MaintenanceRequest::forEstate($estate)->whereIn('status', [MaintenanceStatus::Completed->value, MaintenanceStatus::Closed->value])->where('completed_at', '>=', now()->subDays(30))->count(),
            'queue' => (clone $open)->with('property', 'assignee')->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")->oldest()->limit(6)->get(),
            'assets' => Asset::forEstate($estate)->whereNotNull('next_service_due_on')->where('next_service_due_on', '<=', now()->addDays(14))->orderBy('next_service_due_on')->limit(5)->get(),
            'myTasks' => StaffTask::forEstate($estate)->where('assigned_to', $user->id)->whereIn('status', ['open', 'in_progress'])->orderByRaw('due_at IS NULL')->orderBy('due_at')->limit(5)->get(),
        ];
    }

    public function management(Estate $estate): array
    {
        [$start, $end] = $this->today($estate);
        $properties = Property::forEstate($estate);
        $total = (clone $properties)->count();
        $occupied = (clone $properties)->whereIn('occupancy_status', ['owner_occupied', 'tenanted'])->count();
        $monthStart = now($estate->timezone())->startOfMonth()->utc();

        $billed = (int) Invoice::forEstate($estate)->where('status', '!=', InvoiceStatus::Void->value)->where('issue_date', '>=', $monthStart->toDateString())->sum('total');
        $collected = (int) Payment::forEstate($estate)->where('status', PaymentStatus::Succeeded->value)->where('purpose', 'estate_invoice')->where('paid_at', '>=', $monthStart)->sum('amount_received');

        return [
            'properties' => $total,
            'occupancy' => $total ? (int) round($occupied / $total * 100) : null,
            'residents' => User::whereHas('occupancies', fn ($q) => $q->where('estate_id', $estate->id)->where('status', 'active'))->count(),
            'pendingApprovals' => MembershipRequest::forEstate($estate)->where('status', 'pending')->count(),
            'visitorsToday' => Visit::forEstate($estate)->whereBetween('checked_in_at', [$start, $end])->count(),
            'openIncidents' => Incident::forEstate($estate)->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::Investigating->value])->count(),
            'openMaintenance' => MaintenanceRequest::forEstate($estate)->whereNotIn('status', ['closed', 'cancelled', 'completed'])->count(),
            'overdueInvoices' => Invoice::forEstate($estate)->outstanding()->where('due_date', '<', now()->toDateString())->count(),
            'outstanding' => (int) Invoice::forEstate($estate)->outstanding()->selectRaw('COALESCE(SUM(total - amount_paid), 0) as balance')->value('balance'),
            'billedThisMonth' => $billed,
            'collectedThisMonth' => $collected,
            'powerToday' => (int) PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)->whereBetween('token_issued_at', [$start, $end])->sum('vend_amount'),
            'powerCountToday' => PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)->whereBetween('token_issued_at', [$start, $end])->count(),
            'powerAttention' => PowerPurchase::forEstate($estate)->whereIn('status', [PowerPurchaseStatus::VendFailed->value, PowerPurchaseStatus::ReconciliationRequired->value])->count(),
            'pendingBookings' => Booking::forEstate($estate)->where('status', BookingStatus::Pending->value)->count(),
            'activity' => AuditLog::where('estate_id', $estate->id)->with('user')->latest('id')->limit(8)->get(),
            'powerTrend' => $this->powerTrend($estate),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function powerTrend(Estate $estate, int $days = 14): array
    {
        $tz = $estate->timezone();
        $from = now($tz)->subDays($days - 1)->startOfDay();

        $rows = PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)
            ->where('token_issued_at', '>=', $from->copy()->utc())
            ->get(['token_issued_at', 'vend_amount'])
            ->groupBy(fn ($p) => $p->token_issued_at->timezone($tz)->toDateString())
            ->map(fn ($group) => (int) $group->sum('vend_amount'));

        $labels = [];
        $values = [];
        for ($d = $from->copy(); $d->lte(now($tz)); $d->addDay()) {
            $labels[] = $d->format('j M');
            $values[] = intdiv($rows[$d->toDateString()] ?? 0, 100);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function hourlyEntries(Estate $estate, Carbon $start, Carbon $end): array
    {
        $tz = $estate->timezone();
        $counts = Visit::forEstate($estate)->whereBetween('checked_in_at', [$start, $end])->pluck('checked_in_at')
            ->countBy(fn ($t) => (int) $t->timezone($tz)->format('G'));

        $labels = [];
        $values = [];
        for ($h = 6; $h <= 22; $h++) {
            $labels[] = Carbon::createFromTime($h)->format('ga');
            $values[] = $counts[$h] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function today(Estate $estate): array
    {
        $tz = $estate->timezone();

        return [now($tz)->startOfDay()->utc(), now($tz)->endOfDay()->utc()];
    }
}
