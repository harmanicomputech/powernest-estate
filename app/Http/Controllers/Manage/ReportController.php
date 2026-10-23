<?php

namespace App\Http\Controllers\Manage;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PowerPurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PowerPurchase;
use App\Models\Visit;
use App\Support\EstateContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Monthly operational and financial summary built from live records.
 */
class ReportController extends Controller
{
    public function index(Request $request, EstateContext $context): View
    {
        $estate = $context->estate();
        $tz = $estate->timezone();
        $month = $request->query('month', now($tz)->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01', $tz)->startOfDay();
        $end = $start->copy()->endOfMonth();
        [$s, $e] = [$start->copy()->utc(), $end->copy()->utc()];

        $months = collect(range(5, 0))->map(fn ($i) => now($tz)->startOfMonth()->subMonths($i));

        $series = $months->map(function (Carbon $m) use ($estate) {
            $ms = $m->copy()->utc();
            $me = $m->copy()->endOfMonth()->utc();

            return [
                'label' => $m->format('M'),
                'billed' => intdiv((int) Invoice::forEstate($estate)->where('status', '!=', 'void')->whereBetween('issue_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('total'), 100),
                'collected' => intdiv((int) Payment::forEstate($estate)->where('purpose', 'estate_invoice')->where('status', PaymentStatus::Succeeded->value)->whereBetween('paid_at', [$ms, $me])->sum('amount_received'), 100),
                'expenses' => intdiv((int) Expense::forEstate($estate)->whereIn('status', [ExpenseStatus::Approved->value, ExpenseStatus::Paid->value])->whereBetween('incurred_on', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount'), 100),
            ];
        });

        return view('manage.reports.index', [
            'month' => $month,
            'start' => $start,
            'series' => $series,
            'finance' => [
                'billed' => (int) Invoice::forEstate($estate)->where('status', '!=', 'void')->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])->sum('total'),
                'collected' => (int) Payment::forEstate($estate)->where('purpose', 'estate_invoice')->where('status', PaymentStatus::Succeeded->value)->whereBetween('paid_at', [$s, $e])->sum('amount_received'),
                'expenses' => (int) Expense::forEstate($estate)->whereIn('status', [ExpenseStatus::Approved->value, ExpenseStatus::Paid->value])->whereBetween('incurred_on', [$start->toDateString(), $end->toDateString()])->sum('amount'),
                'expensesByCategory' => Expense::forEstate($estate)->whereIn('status', [ExpenseStatus::Approved->value, ExpenseStatus::Paid->value])->whereBetween('incurred_on', [$start->toDateString(), $end->toDateString()])->selectRaw('category, SUM(amount) as total')->groupBy('category')->pluck('total', 'category'),
            ],
            'power' => [
                'volume' => (int) PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)->whereBetween('token_issued_at', [$s, $e])->sum('vend_amount'),
                'count' => PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)->whereBetween('token_issued_at', [$s, $e])->count(),
                'fees' => (int) PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)->whereBetween('token_issued_at', [$s, $e])->sum('service_charge'),
                'failed' => PowerPurchase::forEstate($estate)->whereIn('status', [PowerPurchaseStatus::VendFailed->value, PowerPurchaseStatus::ReconciliationRequired->value])->whereBetween('created_at', [$s, $e])->count(),
            ],
            'operations' => [
                'visits' => Visit::forEstate($estate)->whereBetween('checked_in_at', [$s, $e])->count(),
                'denied' => Visit::forEstate($estate)->where('status', 'denied')->whereBetween('created_at', [$s, $e])->count(),
                'maintenanceOpened' => MaintenanceRequest::forEstate($estate)->whereBetween('created_at', [$s, $e])->count(),
                'maintenanceClosed' => MaintenanceRequest::forEstate($estate)->whereBetween('completed_at', [$s, $e])->count(),
                'incidents' => Incident::forEstate($estate)->whereBetween('occurred_at', [$s, $e])->count(),
                'incidentsBySeverity' => Incident::forEstate($estate)->whereBetween('occurred_at', [$s, $e])->selectRaw('severity, count(*) as c')->groupBy('severity')->pluck('c', 'severity'),
            ],
        ]);
    }
}
