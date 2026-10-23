<?php

namespace App\Services\Estate;

use App\Enums\EstateRole;
use App\Enums\MembershipStatus;
use App\Enums\OccupancyRelationship;
use App\Enums\Permission;
use App\Models\Estate;
use App\Models\EstateInvitation;
use App\Models\EstateMembership;
use App\Models\MembershipRequest;
use App\Models\Occupancy;
use App\Models\Property;
use App\Models\User;
use App\Notifications\EstateInvitationNotification;
use App\Notifications\MembershipRequestNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function __construct(private OccupancyService $occupancies, private AuditLogger $audit) {}

    /**
     * Creates an invitation and emails a single-use link. Only the token hash is stored.
     */
    public function invite(Estate $estate, string $email, EstateRole $role, User $inviter, ?string $name = null, ?Property $property = null, ?OccupancyRelationship $relationship = null): EstateInvitation
    {
        $email = Str::lower(trim($email));

        if ($role->isCommunity() && (! $property || ! $relationship)) {
            throw ValidationException::withMessages(['property_id' => 'Choose the property this person is linked to.']);
        }

        if (EstateInvitation::where('estate_id', $estate->id)->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['email' => 'An invitation for this email is already pending.']);
        }

        $token = Str::random(48);

        $invitation = EstateInvitation::create([
            'estate_id' => $estate->id,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'property_id' => $property?->id,
            'relationship' => $relationship,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $email)->notify(new EstateInvitationNotification($invitation, $token));
        $this->audit->record('invitation.sent', $invitation, ['email' => $email, 'role' => $role->value], $estate, $inviter);

        return $invitation;
    }

    public function findInvitation(string $token): ?EstateInvitation
    {
        return EstateInvitation::with('estate', 'property')->where('token_hash', hash('sha256', $token))->first();
    }

    public function invitationProblem(EstateInvitation $invitation): ?string
    {
        return match (true) {
            $invitation->accepted_at !== null => 'This invitation has already been used.',
            $invitation->revoked_at !== null => 'This invitation was withdrawn by the estate.',
            $invitation->expires_at->isPast() => 'This invitation has expired. Ask the estate to send a new one.',
            ! $invitation->estate->isOperational() => 'This estate is not currently active on PowerNest Estate.',
            default => null,
        };
    }

    public function accept(EstateInvitation $invitation, User $user): EstateMembership
    {
        return DB::transaction(function () use ($invitation, $user) {
            $invitation = EstateInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($problem = $this->invitationProblem($invitation)) {
                throw ValidationException::withMessages(['invitation' => $problem]);
            }

            if (! hash_equals(Str::lower($invitation->email), Str::lower($user->email))) {
                throw ValidationException::withMessages(['invitation' => "This invitation was sent to {$invitation->email}. Sign in with that email to accept it."]);
            }

            $inviter = $invitation->inviter;

            if ($invitation->property_id && $invitation->relationship) {
                $primary = null;

                if ($invitation->relationship === OccupancyRelationship::HouseholdMember) {
                    $primary = Occupancy::where('property_id', $invitation->property_id)->where('user_id', $invitation->invited_by)->where('status', 'active')->where('is_primary', true)->first()
                        ?? Occupancy::where('property_id', $invitation->property_id)->where('status', 'active')->where('is_primary', true)->first();
                }

                $this->occupancies->addOccupant($invitation->property, $user, $invitation->relationship, $inviter, $primary);
            }

            $membership = $this->occupancies->ensureMembership($invitation->estate_id, $user, $invitation->role, $inviter);
            $invitation->update(['accepted_at' => now(), 'accepted_user_id' => $user->id]);
            $user->forceFill(['current_estate_id' => $invitation->estate_id])->save();

            $this->audit->record('invitation.accepted', $invitation, ['role' => $invitation->role->value], $invitation->estate_id, $user);

            return $membership;
        });
    }

    public function requestAccess(User $user, Estate $estate, Property $property, OccupancyRelationship $relationship, ?string $message): MembershipRequest
    {
        if (MembershipRequest::where('estate_id', $estate->id)->where('user_id', $user->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['estate_code' => 'You already have a pending request for this estate.']);
        }

        $request = MembershipRequest::create([
            'estate_id' => $estate->id,
            'user_id' => $user->id,
            'property_id' => $property->id,
            'relationship' => $relationship,
            'message' => $message,
        ]);

        $managers = $this->usersWithPermission($estate, Permission::MembersManage);
        Notification::send($managers, new MembershipRequestNotification($request));
        $this->audit->record('membership.requested', $request, ['property' => $property->identifier], $estate, $user);

        return $request;
    }

    public function approve(MembershipRequest $request, User $reviewer): void
    {
        DB::transaction(function () use ($request, $reviewer) {
            $request = MembershipRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless($request->status === 'pending', 409, 'This request has already been reviewed.');

            $primary = null;
            if ($request->relationship === OccupancyRelationship::HouseholdMember) {
                $primary = Occupancy::where('property_id', $request->property_id)->where('status', 'active')->where('is_primary', true)->first();
            }

            $this->occupancies->addOccupant($request->property, $request->user, $request->relationship, $reviewer, $primary);
            $request->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            $this->audit->record('membership.approved', $request, [], $request->estate_id, $reviewer);
        });
    }

    public function reject(MembershipRequest $request, User $reviewer, ?string $note): void
    {
        abort_unless($request->status === 'pending', 409, 'This request has already been reviewed.');
        $request->update(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
        $this->audit->record('membership.rejected', $request, [], $request->estate_id, $reviewer);
    }

    public function setStatus(EstateMembership $membership, MembershipStatus $status, User $actor): void
    {
        if ($membership->user_id === $actor->id) {
            throw ValidationException::withMessages(['membership' => 'You cannot change your own access.']);
        }

        $membership->update(['status' => $status]);
        $this->audit->record('membership.'.$status->value, $membership, ['role' => $membership->role->value, 'user_id' => $membership->user_id], $membership->estate_id, $actor);
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function usersWithPermission(Estate $estate, Permission $permission)
    {
        $roles = collect(EstateRole::cases())->filter(fn (EstateRole $r) => in_array($permission, $r->permissions(), true))->map->value;

        return User::where('status', 'active')
            ->whereHas('memberships', fn ($q) => $q->where('estate_id', $estate->id)->where('status', MembershipStatus::Active->value)->whereIn('role', $roles))
            ->get();
    }
}
