<?php

namespace App\Http\Controllers\Manage;

use App\Enums\EstateRole;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\EstateInvitation;
use App\Models\EstateMembership;
use App\Models\MembershipRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Estate\MembershipService;
use App\Services\Estate\OccupancyService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function __construct(private EstateContext $context, private MembershipService $members, private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();
        $tab = $request->query('tab', 'staff');

        $staffRoles = collect(EstateRole::cases())->filter->isStaff()->map->value->all();

        return view('manage.members.index', [
            'tab' => $tab,
            'staff' => EstateMembership::forEstate($estate)->whereIn('role', $staffRoles)->where('status', '!=', MembershipStatus::Revoked->value)->with('user')->orderBy('role')->get(),
            'invitations' => EstateInvitation::forEstate($estate)->whereNull('accepted_at')->whereNull('revoked_at')->with('property', 'inviter')->latest()->get(),
            'requests' => MembershipRequest::forEstate($estate)->where('status', 'pending')->with('user', 'property')->oldest()->get(),
            'staffRoles' => collect(EstateRole::cases())->filter->isStaff()->reject(fn ($r) => $r === EstateRole::EstateOwner && ! $request->user()->hasRoleIn($estate, EstateRole::EstateOwner))->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(collect(EstateRole::cases())->filter->isStaff()->map->value->all())],
        ]);

        $role = EstateRole::from($data['role']);
        abort_if($role === EstateRole::EstateOwner && ! $request->user()->hasRoleIn($estate, EstateRole::EstateOwner), 403, 'Only an estate owner can invite another owner.');

        // Someone already in this estate (e.g. a resident) gets the role straight away; anyone else is invited.
        $existing = User::where('email', Str::lower(trim($data['email'])))->first();
        if ($existing && $existing->isMemberOf($estate)) {
            app(OccupancyService::class)->ensureMembership($estate->id, $existing, $role, $request->user());
            $this->audit->record('membership.role_granted', $existing, ['role' => $role->value], $estate, $request->user());

            return back()->with('success', "{$existing->name} is now {$role->label()}.");
        }

        $this->members->invite($estate, $data['email'], $role, $request->user(), $data['name'] ?? null);

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    public function changeRole(EstateMembership $membership, Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate(['role' => ['required', Rule::in(collect(EstateRole::cases())->filter->isStaff()->map->value->all())]]);
        $role = EstateRole::from($data['role']);
        $actorIsOwner = $request->user()->hasRoleIn($estate, EstateRole::EstateOwner);

        abort_if($membership->user_id === $request->user()->id, 403, 'You cannot change your own role.');
        abort_if(($role === EstateRole::EstateOwner || $membership->role === EstateRole::EstateOwner) && ! $actorIsOwner, 403, 'Only an estate owner can grant or remove the owner role.');

        if ($membership->role === $role) {
            return back();
        }

        if (EstateMembership::where('estate_id', $estate->id)->where('user_id', $membership->user_id)->where('role', $role->value)->exists()) {
            $this->members->setStatus($membership, MembershipStatus::Revoked, $request->user());
            app(OccupancyService::class)->ensureMembership($estate->id, $membership->user, $role, $request->user());
        } else {
            $old = $membership->role;
            $membership->update(['role' => $role, 'status' => MembershipStatus::Active]);
            $membership->user->flushRoleCache();
            $this->audit->record('membership.role_changed', $membership, ['from' => $old->value, 'to' => $role->value], $estate, $request->user());
        }

        return back()->with('success', "{$membership->user->name} is now {$role->label()}.");
    }

    public function status(EstateMembership $membership, Request $request): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in([MembershipStatus::Active->value, MembershipStatus::Suspended->value, MembershipStatus::Revoked->value])]]);
        abort_if($membership->role === EstateRole::EstateOwner && ! $request->user()->hasRoleIn($membership->estate_id, EstateRole::EstateOwner), 403);
        abort_if($membership->user_id === $request->user()->id, 403, 'You cannot change your own access.');

        $this->members->setStatus($membership, MembershipStatus::from($data['status']), $request->user());

        return back()->with('success', 'Access updated.');
    }

    public function revokeInvitation(EstateInvitation $invitation, Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_if($invitation->accepted_at, 409);
        $invitation->update(['revoked_at' => now()]);
        $audit->record('invitation.revoked', $invitation, ['email' => $invitation->email], $invitation->estate_id);

        return back()->with('success', 'Invitation withdrawn.');
    }

    public function review(MembershipRequest $membershipRequest, Request $request): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:500']]);

        if ($data['decision'] === 'approve') {
            $this->members->approve($membershipRequest, $request->user());
        } else {
            $this->members->reject($membershipRequest, $request->user(), $data['note'] ?? null);
        }

        return back()->with('success', $data['decision'] === 'approve' ? "{$membershipRequest->user->name} now has access." : 'Request declined.');
    }
}
