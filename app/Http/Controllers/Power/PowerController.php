<?php

namespace App\Http\Controllers\Power;

use App\Enums\PowerPurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\PowerPurchase;
use App\Support\EstateContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PowerController extends Controller
{
    public function index(Request $request, EstateContext $context): View
    {
        $user = $request->user();
        $estate = $context->estate();
        $propertyIds = $user->authorizedProperties($estate)->pluck('properties.id');
        $favourites = $user->favouriteMeters()->pluck('meters.id');

        $meters = Meter::forEstate($estate)->whereIn('property_id', $propertyIds)->where('status', 'active')->with('property')->get()
            ->sortByDesc(fn (Meter $m) => $favourites->contains($m->id))->values();

        $purchases = PowerPurchase::forEstate($estate)->where('user_id', $user->id)->with('token')->latest()->limit(5)->get();

        $monthSpend = PowerPurchase::forEstate($estate)->where('user_id', $user->id)
            ->where('status', PowerPurchaseStatus::TokenIssued->value)->where('created_at', '>=', now()->startOfMonth())->sum('vend_amount');

        return view('power.index', [
            'meters' => $meters,
            'favourites' => $favourites,
            'purchases' => $purchases,
            'monthSpend' => (int) $monthSpend,
            'properties' => $user->authorizedProperties($estate)->orderBy('identifier')->get(),
            // Old-app purchases without a token are reviewed by staff, not shown to residents as live alerts.
            'attention' => PowerPurchase::forEstate($estate)->where('user_id', $user->id)->whereIn('status', [PowerPurchaseStatus::Paid->value, PowerPurchaseStatus::Vending->value, PowerPurchaseStatus::VendFailed->value, PowerPurchaseStatus::ReconciliationRequired->value])
                ->where(fn ($q) => $q->whereNull('failure_code')->orWhere('failure_code', '!=', 'legacy_no_token'))->latest()->get(),
        ]);
    }
}
