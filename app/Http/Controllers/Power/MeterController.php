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
            'meter_number' => ['required', 'string', 'regex:/^[0-9 -]{8,24}$/'],
        ], ['meter_number.regex' => 'Meter numbers are 8–20 digits.']);

        $meter = $meters->lookup($request->user(), $context->estate(), $data['meter_number']);

        return redirect()->route('power.buy', ['meter' => $meter->id]);
    }

    public function favourite(Meter $meter, Request $request): RedirectResponse
    {
        abort_unless($meter->estate_id === app(EstateContext::class)->estate()->id, 404);
        $request->user()->favouriteMeters()->toggle([$meter->id]);

        return back();
    }
}
