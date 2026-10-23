<?php

namespace App\Http\Controllers;

use App\Enums\OccupancyRelationship;
use App\Models\Estate;
use App\Models\MembershipRequest;
use App\Models\Property;
use App\Services\Estate\MembershipService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request, EstateContext $context): View|RedirectResponse
    {
        if ($context->hasEstate()) {
            return redirect()->route('dashboard');
        }

        return view('onboarding', [
            'requests' => MembershipRequest::with('estate', 'property')->where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function requestAccess(Request $request, MembershipService $members): RedirectResponse
    {
        $data = $request->validate([
            'estate_code' => ['required', 'string', 'max:12'],
            'property_identifier' => ['required', 'string', 'max:40'],
            'relationship' => ['required', Rule::enum(OccupancyRelationship::class)],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $estate = Estate::where('code', strtoupper(trim($data['estate_code'])))->where('status', 'active')->first();
        $property = $estate ? Property::where('estate_id', $estate->id)->where('identifier', strtoupper(trim($data['property_identifier'])))->first() : null;

        // Same message for both failures, so estate codes and properties cannot be enumerated.
        if (! $estate || ! $property) {
            return back()->withInput()->withErrors(['estate_code' => 'We could not match that estate code and property. Check with your estate office.']);
        }

        $members->requestAccess($request->user(), $estate, $property, OccupancyRelationship::from($data['relationship']), $data['message'] ?? null);

        return back()->with('success', "Request sent to {$estate->name}. You'll be notified when it's approved.");
    }
}
