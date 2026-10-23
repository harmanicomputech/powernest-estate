<?php

namespace App\Http\Controllers;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\Permission;
use App\Models\Incident;
use App\Models\User;
use App\Services\Estate\MembershipService;
use App\Services\Operations\IncidentService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public const CATEGORIES = ['security_breach' => 'Security breach', 'theft' => 'Theft', 'vandalism' => 'Vandalism', 'fire' => 'Fire', 'medical' => 'Medical', 'noise' => 'Noise / nuisance', 'traffic' => 'Traffic / parking', 'suspicious' => 'Suspicious activity', 'safety_hazard' => 'Safety hazard', 'lost_found' => 'Lost & found', 'other' => 'Other'];

    public function __construct(private EstateContext $context, private IncidentService $service) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $estate = $this->context->estate();
        $isManager = $user->hasEstatePermission($estate, Permission::IncidentsManage);
        $tab = $request->query('tab', 'open');

        $incidents = Incident::forEstate($estate)->with('reporter', 'assignee')
            ->when(! $isManager, fn ($q) => $q->where(fn ($w) => $w->where('reported_by', $user->id)->orWhere('assigned_to', $user->id)))
            ->when($tab === 'open', fn ($q) => $q->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::Investigating->value]))
            ->when($tab === 'closed', fn ($q) => $q->whereIn('status', [IncidentStatus::Resolved->value, IncidentStatus::Closed->value]))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest('occurred_at')->paginate(20)->withQueryString();

        return view('incidents.index', compact('incidents', 'tab', 'isManager'));
    }

    public function create(Request $request): View
    {
        return view('incidents.create', [
            'categories' => self::CATEGORIES,
            'properties' => $request->user()->authorizedProperties($this->context->estate())->orderBy('identifier')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'title' => ['required', 'string', 'max:140'],
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:160'],
            'property_id' => ['nullable', 'integer'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'evidence' => ['nullable', 'array', 'max:4'],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $estate = $this->context->estate();
        if (! empty($data['property_id']) && ! $request->user()->authorizedProperties($estate)->whereKey($data['property_id'])->exists() && ! Gate::allows(Permission::IncidentsManage->value)) {
            $data['property_id'] = null;
        }
        $data['occurred_at'] = ! empty($data['occurred_at']) ? Carbon::parse($data['occurred_at'], $estate->timezone())->utc() : now();

        $incident = $this->service->report($request->user(), $estate, $data, $request->file('evidence', []));

        return redirect()->route('incidents.show', $incident)->with('success', "Incident {$incident->reference} reported.");
    }

    public function show(Incident $incident): View
    {
        Gate::authorize('view', $incident);
        $estate = $this->context->estate();

        return view('incidents.show', [
            'incident' => $incident->load('reporter', 'assignee', 'property', 'documents'),
            'updates' => $incident->updates()->with('user')->latest('id')->get(),
            'canManage' => Gate::allows('manage', $incident),
            'canNote' => Gate::allows('addNote', $incident),
            'staff' => Gate::allows('manage', $incident) ? app(MembershipService::class)->usersWithPermission($estate, Permission::IncidentsReport)->filter(fn (User $u) => $u->rolesIn($estate)->contains(fn ($r) => $r->isStaff()))->values() : collect(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function assign(Incident $incident, Request $request): RedirectResponse
    {
        Gate::authorize('manage', $incident);
        $data = $request->validate(['assigned_to' => ['required', 'integer']]);
        $this->service->assign($incident, $request->user(), User::findOrFail($data['assigned_to']));

        return back()->with('success', 'Incident assigned.');
    }

    public function status(Incident $incident, Request $request): RedirectResponse
    {
        Gate::authorize('manage', $incident);
        $data = $request->validate(['status' => ['required', Rule::enum(IncidentStatus::class)], 'note' => ['nullable', 'string', 'max:2000', 'required_if:status,resolved']]);
        $this->service->setStatus($incident, $request->user(), IncidentStatus::from($data['status']), $data['note'] ?? null);

        return back()->with('success', 'Incident updated.');
    }

    public function note(Incident $incident, Request $request): RedirectResponse
    {
        Gate::authorize('addNote', $incident);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $this->service->update($incident, $request->user(), 'note', $data['body']);

        return back()->with('success', 'Note added.');
    }
}
