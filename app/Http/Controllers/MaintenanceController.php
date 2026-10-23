<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Enums\Permission;
use App\Enums\Priority;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Operations\MaintenanceService;
use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public const CATEGORIES = ['plumbing' => 'Plumbing', 'electrical' => 'Electrical', 'carpentry' => 'Carpentry', 'painting' => 'Painting', 'roofing' => 'Roofing', 'hvac' => 'Air conditioning', 'drainage' => 'Drainage', 'common_area' => 'Common area', 'security_equipment' => 'Security equipment', 'other' => 'Other'];

    public function __construct(private EstateContext $context, private MaintenanceService $service) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $estate = $this->context->estate();
        $isManager = $user->hasEstatePermission($estate, Permission::MaintenanceManage);
        $isWorker = $user->hasEstatePermission($estate, Permission::MaintenanceWork);
        $tab = $request->query('tab', 'open');

        $query = MaintenanceRequest::forEstate($estate)->with('property', 'assignee', 'requester');

        if (! $isManager) {
            $propertyIds = $user->authorizedProperties($estate)->pluck('properties.id');
            $query->where(fn ($q) => $q->where('requested_by', $user->id)
                ->orWhereIn('property_id', $propertyIds)
                ->when($isWorker, fn ($w) => $w->orWhere('assigned_to', $user->id)));
        }

        $counts = [
            'open' => (clone $query)->whereNotIn('status', ['closed', 'cancelled', 'completed'])->count(),
            'mine' => $isWorker ? (clone $query)->where('assigned_to', $user->id)->whereNotIn('status', ['closed', 'cancelled'])->count() : null,
            'overdue' => $isManager ? (clone $query)->whereNotIn('status', ['closed', 'cancelled', 'completed'])->where('due_at', '<', now())->count() : null,
        ];

        $requests = $query
            ->when($tab === 'open', fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled', 'completed']))
            ->when($tab === 'mine', fn ($q) => $q->where('assigned_to', $user->id)->whereNotIn('status', ['closed', 'cancelled']))
            ->when($tab === 'overdue', fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled', 'completed'])->where('due_at', '<', now()))
            ->when($tab === 'done', fn ($q) => $q->whereIn('status', ['closed', 'cancelled', 'completed']))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->latest()->paginate(20)->withQueryString();

        return view('maintenance.index', compact('requests', 'tab', 'counts', 'isManager', 'isWorker'));
    }

    public function create(Request $request): View
    {
        Gate::authorize(Permission::MaintenanceRequest->value);
        $estate = $this->context->estate();

        return view('maintenance.create', [
            'properties' => $request->user()->authorizedProperties($estate)->orderBy('identifier')->get(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::MaintenanceRequest->value);

        $data = $request->validate([
            'property_id' => ['nullable', 'integer'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'location' => ['nullable', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:3000'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'preferred_time' => ['nullable', 'string', 'max:120'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ]);

        $maintenance = $this->service->submit($request->user(), $this->context->estate(), $data, $request->file('photos', []));

        return redirect()->route('maintenance.show', $maintenance)->with('success', "Request {$maintenance->reference} submitted.");
    }

    public function show(MaintenanceRequest $maintenance, Request $request): View
    {
        Gate::authorize('view', $maintenance);
        $estate = $this->context->estate();
        $canSeeInternal = Gate::allows('viewInternal', $maintenance);

        return view('maintenance.show', [
            'request' => $maintenance->load(['property', 'requester', 'assignee', 'vendor', 'documents' => fn ($q) => $canSeeInternal ? $q : $q->where('category', '!=', 'internal')]),
            'updates' => $maintenance->updates()->with('user')->when(! $canSeeInternal, fn ($q) => $q->where('is_internal', false))->get(),
            'canManage' => Gate::allows('manage', $maintenance),
            'canWork' => Gate::allows('work', $maintenance),
            'canSeeInternal' => $canSeeInternal,
            'staff' => Gate::allows('manage', $maintenance) ? $this->workers($estate) : collect(),
            'vendors' => Gate::allows('manage', $maintenance) ? Vendor::forEstate($estate)->where('is_active', true)->orderBy('name')->get() : collect(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function comment(MaintenanceRequest $maintenance, Request $request): RedirectResponse
    {
        Gate::authorize('view', $maintenance);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'internal' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ]);

        $internal = $request->boolean('internal') && Gate::allows('viewInternal', $maintenance);
        $this->service->comment($maintenance, $request->user(), $data['body'], $internal, $request->file('photos', []));

        return back()->with('success', 'Update posted.');
    }

    public function assign(MaintenanceRequest $maintenance, Request $request): RedirectResponse
    {
        Gate::authorize('manage', $maintenance);
        $estate = $this->context->estate();
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'due_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date'],
        ]);

        if (empty($data['assigned_to']) && empty($data['vendor_id'])) {
            return back()->withErrors(['assigned_to' => 'Choose a staff member or a vendor.']);
        }

        $assignee = ! empty($data['assigned_to']) ? User::findOrFail($data['assigned_to']) : null;
        $vendorId = ! empty($data['vendor_id']) ? Vendor::forEstate($estate)->findOrFail($data['vendor_id'])->id : null;
        $tz = $estate->timezone();

        $this->service->assign($maintenance, $request->user(), $assignee, $vendorId, Priority::from($data['priority']),
            ! empty($data['due_at']) ? Carbon::parse($data['due_at'], $tz)->utc() : null,
            ! empty($data['scheduled_for']) ? Carbon::parse($data['scheduled_for'], $tz)->utc() : null);

        return back()->with('success', 'Assignment saved.');
    }

    public function status(MaintenanceRequest $maintenance, Request $request): RedirectResponse
    {
        Gate::authorize('work', $maintenance);
        $data = $request->validate(['status' => ['required', Rule::enum(MaintenanceStatus::class)], 'note' => ['nullable', 'string', 'max:1000']]);
        $to = MaintenanceStatus::from($data['status']);

        abort_if(in_array($to, [MaintenanceStatus::Closed, MaintenanceStatus::Cancelled, MaintenanceStatus::Assigned], true) && ! Gate::allows('manage', $maintenance), 403);

        $this->service->transition($maintenance, $to, $request->user(), $data['note'] ?? null);

        return back()->with('success', "Marked as {$to->label()}.");
    }

    public function costs(MaintenanceRequest $maintenance, Request $request): RedirectResponse
    {
        Gate::authorize('work', $maintenance);
        $data = $request->validate(['labour_cost' => ['required', 'string', 'max:15'], 'parts_cost' => ['required', 'string', 'max:15']]);
        $this->service->recordCosts($maintenance, $request->user(), Money::parse($data['labour_cost']), Money::parse($data['parts_cost']));

        return back()->with('success', 'Costs recorded.');
    }

    public function confirm(MaintenanceRequest $maintenance, Request $request): RedirectResponse
    {
        $data = $request->validate(['resolved' => ['required', 'boolean'], 'comment' => ['nullable', 'string', 'max:1000', 'required_if:resolved,0']]);
        $this->service->residentResponse($maintenance, $request->user(), $request->boolean('resolved'), $data['comment'] ?? null);

        return back()->with('success', $request->boolean('resolved') ? 'Thanks for confirming. Request closed.' : 'Request reopened. The team has been notified.');
    }

    private function workers($estate)
    {
        return app(\App\Services\Estate\MembershipService::class)->usersWithPermission($estate, Permission::MaintenanceWork)->sortBy('name')->values();
    }
}
