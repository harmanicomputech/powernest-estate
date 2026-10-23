<?php

namespace App\Http\Controllers;

use App\Enums\EstateRole;
use App\Enums\OccupancyRelationship;
use App\Models\MoveRequest;
use App\Models\Occupancy;
use App\Services\Estate\MembershipService;
use App\Services\Estate\OccupancyService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function __construct(private EstateContext $context) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $estate = $this->context->estate();

        $occupancies = Occupancy::forEstate($estate)->where('user_id', $user->id)->where('status', 'active')
            ->with(['property.building', 'property.documents' => fn ($q) => $q->where('category', 'property_shared'), 'property.occupancies' => fn ($q) => $q->where('status', 'active')->with('user')])
            ->get();

        return view('household.index', [
            'occupancies' => $occupancies,
            'moveRequests' => MoveRequest::forEstate($estate)->where('user_id', $user->id)->with('property')->latest()->limit(5)->get(),
        ]);
    }

    public function invite(Request $request, MembershipService $members): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
        ]);

        $primary = Occupancy::forEstate($estate)->where('user_id', $request->user()->id)->where('property_id', $data['property_id'])
            ->where('status', 'active')->where('is_primary', true)->with('property')->first();

        abort_unless($primary && in_array($primary->relationship, [OccupancyRelationship::Owner, OccupancyRelationship::Tenant], true), 403, 'Only the primary resident can add household members.');

        $members->invite($estate, $data['email'], EstateRole::HouseholdMember, $request->user(), $data['name'], $primary->property, OccupancyRelationship::HouseholdMember);

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    public function remove(Occupancy $occupancy, Request $request, OccupancyService $occupancies): RedirectResponse
    {
        $primary = $occupancy->primaryOccupancy;
        abort_unless($occupancy->relationship === OccupancyRelationship::HouseholdMember && $primary && $primary->user_id === $request->user()->id, 403);

        $occupancies->end($occupancy, $request->user());

        return back()->with('success', 'Household member removed. Their access has ended.');
    }

    public function moveRequest(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(['move_in', 'move_out'])],
            'scheduled_for' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($request->user()->authorizedProperties($estate)->whereKey($data['property_id'])->exists(), 403);

        MoveRequest::create($data + ['estate_id' => $estate->id, 'user_id' => $request->user()->id, 'status' => 'pending']);

        return back()->with('success', 'Move request sent to estate management.');
    }
}
