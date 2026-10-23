<?php

namespace App\Http\Controllers\Manage;

use App\Enums\EstateRole;
use App\Enums\OccupancyRelationship;
use App\Enums\OccupancyStatus;
use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\MoveRequest;
use App\Models\Occupancy;
use App\Models\Property;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingService;
use App\Services\Estate\MembershipService;
use App\Services\Estate\OccupancyService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function __construct(private EstateContext $context, private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');
        $building = $request->query('building');

        $properties = Property::forEstate($estate)->with(['building', 'occupancies' => fn ($q) => $q->where('status', 'active')->where('is_primary', true)->with('user')])
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('identifier', 'like', "%{$search}%")->orWhere('address_line', 'like', "%{$search}%")))
            ->when($status, fn ($q) => $q->where('occupancy_status', $status))
            ->when($building, fn ($q) => $q->where('building_id', $building))
            ->orderBy('identifier')->paginate(30)->withQueryString();

        $counts = Property::forEstate($estate)->selectRaw('occupancy_status, count(*) as c')->groupBy('occupancy_status')->pluck('c', 'occupancy_status');

        return view('manage.properties.index', [
            'properties' => $properties,
            'buildings' => Building::forEstate($estate)->withCount('properties')->orderBy('name')->get(),
            'counts' => $counts,
            'moveRequests' => MoveRequest::forEstate($estate)->where('status', 'pending')->with('property', 'user')->oldest('scheduled_for')->get(),
            'search' => $search, 'status' => $status, 'building' => $building,
        ]);
    }

    public function create(): View
    {
        return view('manage.properties.create', ['buildings' => Building::forEstate($this->context->estate())->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-\/ ]+$/', Rule::unique('properties')->where('estate_id', $estate->id)],
            'building_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(PropertyType::class)],
            'floor' => ['nullable', 'string', 'max:10'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'address_line' => ['nullable', 'string', 'max:160'],
        ]);

        $data['identifier'] = Str::upper(trim($data['identifier']));
        $data['building_id'] = ! empty($data['building_id']) ? Building::forEstate($estate)->findOrFail($data['building_id'])->id : null;

        $property = Property::create($data + ['estate_id' => $estate->id, 'occupancy_status' => OccupancyStatus::Vacant]);
        $this->audit->record('property.created', $property, ['identifier' => $property->identifier], $estate);

        return redirect()->route('manage.properties.show', $property)->with('success', "{$property->identifier} added.");
    }

    public function storeBuilding(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:20', Rule::unique('buildings')->where('estate_id', $estate->id)],
            'type' => ['required', Rule::in(['block', 'street', 'zone', 'tower'])],
            'floors' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $building = Building::create($data + ['estate_id' => $estate->id]);
        $this->audit->record('building.created', $building, ['code' => $building->code], $estate);

        return back()->with('success', "{$building->name} added.");
    }

    public function show(Property $property, BillingService $billing): View
    {
        $estate = $this->context->estate();

        return view('manage.properties.show', [
            'property' => $property->load('building', 'meters'),
            'occupants' => $property->occupancies()->where('status', 'active')->with('user', 'primaryOccupancy.user')->get(),
            'history' => $property->occupancies()->where('status', 'ended')->with('user')->latest('ends_on')->limit(10)->get(),
            'invoices' => $property->invoices()->latest('issue_date')->limit(5)->get(),
            'balance' => $billing->propertyBalance($property),
            'buildings' => Building::forEstate($estate)->orderBy('name')->get(),
        ]);
    }

    public function update(Property $property, Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'building_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(PropertyType::class)],
            'floor' => ['nullable', 'string', 'max:10'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'address_line' => ['nullable', 'string', 'max:160'],
            'under_renovation' => ['nullable', 'boolean'],
        ]);

        $data['building_id'] = ! empty($data['building_id']) ? Building::forEstate($estate)->findOrFail($data['building_id'])->id : null;
        $renovation = $request->boolean('under_renovation');
        unset($data['under_renovation']);

        $property->update($data + ($renovation ? ['occupancy_status' => OccupancyStatus::UnderRenovation] : []));
        if (! $renovation && $property->occupancy_status === OccupancyStatus::UnderRenovation) {
            $property->update(['occupancy_status' => OccupancyStatus::Vacant]);
            app(OccupancyService::class)->refreshPropertyStatus($property);
        }

        $this->audit->record('property.updated', $property, array_keys($property->getChanges()), $estate);

        return back()->with('success', 'Property updated.');
    }

    /**
     * Link an existing account directly, or invite by email when no account exists yet.
     */
    public function addOccupant(Property $property, Request $request, OccupancyService $occupancies, MembershipService $members): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'name' => ['nullable', 'string', 'max:120'],
            'relationship' => ['required', Rule::in([OccupancyRelationship::Owner->value, OccupancyRelationship::Landlord->value, OccupancyRelationship::Tenant->value])],
            'starts_on' => ['nullable', 'date'],
        ]);

        $relationship = OccupancyRelationship::from($data['relationship']);
        $user = User::where('email', Str::lower($data['email']))->first();

        if ($user) {
            $occupancies->addOccupant($property, $user, $relationship, $request->user(), null, $data['starts_on'] ?? null);

            return back()->with('success', "{$user->name} linked to {$property->identifier}.");
        }

        $members->invite($estate, $data['email'], $occupancies->roleFor($relationship), $request->user(), $data['name'] ?? null, $property, $relationship);

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    public function endOccupancy(Occupancy $occupancy, Request $request, OccupancyService $occupancies): RedirectResponse
    {
        $occupancies->end($occupancy, $request->user());

        return back()->with('success', 'Occupancy ended. Access for this person (and their household) has been removed.');
    }

    public function reviewMove(MoveRequest $moveRequest, Request $request): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected', 'completed'])], 'note' => ['nullable', 'string', 'max:500']]);
        $moveRequest->update(['status' => $data['decision'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['note'] ?? null]);
        $this->audit->record('move_request.'.$data['decision'], $moveRequest, [], $moveRequest->estate_id);

        return back()->with('success', 'Move request updated.');
    }
}
