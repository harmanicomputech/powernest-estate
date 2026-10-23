<?php

namespace App\Http\Controllers\Power;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Services\Power\MeterService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MeterController extends Controller
{
    public function store(Request $request, MeterService $meters, EstateContext $context): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'meter_number' => ['required', 'string', 'regex:/^[0-9 ]{8,20}$/'],
            'label' => ['nullable', 'string', 'max:60'],
        ], ['meter_number.regex' => 'Meter numbers are 8–20 digits.']);

        $meter = $meters->register($request->user(), $context->estate(), (int) $data['property_id'], $data['meter_number'], $data['label'] ?? null);

        return redirect()->route('power.buy', ['meter' => $meter->id])->with(
            $meter->validated_at ? 'success' : 'warning',
            $meter->validated_at ? "Meter {$meter->meter_number} verified".($meter->vendor_customer_name ? " for {$meter->vendor_customer_name}." : '.') : 'Meter saved. It could not be verified with the vending service right now.',
        );
    }

    public function favourite(Meter $meter, Request $request): RedirectResponse
    {
        abort_unless($request->user()->authorizedProperties($meter->estate_id)->whereKey($meter->property_id)->exists(), 403);
        $request->user()->favouriteMeters()->toggle([$meter->id]);

        return back();
    }
}
