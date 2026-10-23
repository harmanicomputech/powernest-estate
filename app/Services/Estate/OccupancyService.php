<?php

namespace App\Services\Estate;

use App\Enums\EstateRole;
use App\Enums\MembershipStatus;
use App\Enums\OccupancyRelationship;
use App\Enums\OccupancyStatus;
use App\Models\EstateMembership;
use App\Models\Occupancy;
use App\Models\Property;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OccupancyService
{
    public function __construct(private AuditLogger $audit) {}

    public function addOccupant(Property $property, User $user, OccupancyRelationship $relationship, ?User $actor = null, ?Occupancy $primary = null, ?string $startsOn = null): Occupancy
    {
        return DB::transaction(function () use ($property, $user, $relationship, $actor, $primary, $startsOn) {
            $existing = Occupancy::where('property_id', $property->id)->where('user_id', $user->id)->where('status', 'active')->lockForUpdate()->first();

            if ($existing) {
                throw ValidationException::withMessages(['email' => "{$user->name} is already linked to {$property->identifier}."]);
            }

            if ($relationship === OccupancyRelationship::HouseholdMember && ! $primary) {
                throw ValidationException::withMessages(['relationship' => 'Household members must be linked to a primary resident.']);
            }

            $occupancy = Occupancy::create([
                'estate_id' => $property->estate_id,
                'property_id' => $property->id,
                'user_id' => $user->id,
                'relationship' => $relationship,
                'primary_occupancy_id' => $primary?->id,
                'is_primary' => $relationship !== OccupancyRelationship::HouseholdMember,
                'status' => 'active',
                'starts_on' => $startsOn ?? now()->toDateString(),
                'created_by' => $actor?->id,
            ]);

            $this->ensureMembership($property->estate_id, $user, $this->roleFor($relationship), $actor);
            $this->refreshPropertyStatus($property);

            $this->audit->record('occupancy.added', $occupancy, ['property' => $property->identifier, 'relationship' => $relationship->value, 'user_id' => $user->id], $property->estate_id, $actor);

            return $occupancy;
        });
    }

    public function end(Occupancy $occupancy, ?User $actor = null): void
    {
        DB::transaction(function () use ($occupancy, $actor) {
            $toEnd = collect([$occupancy])->merge($occupancy->householdMembers()->where('status', 'active')->get());

            foreach ($toEnd as $record) {
                $record->update(['status' => 'ended', 'ends_on' => now()->toDateString(), 'ended_by' => $actor?->id]);
                $this->revokeCommunityRoleIfUnlinked($record);
            }

            $this->refreshPropertyStatus($occupancy->property);
            $this->audit->record('occupancy.ended', $occupancy, ['property' => $occupancy->property->identifier, 'cascaded' => $toEnd->count() - 1], $occupancy->estate_id, $actor);
        });
    }

    public function roleFor(OccupancyRelationship $relationship): EstateRole
    {
        return match ($relationship) {
            OccupancyRelationship::Landlord => EstateRole::Landlord,
            OccupancyRelationship::HouseholdMember => EstateRole::HouseholdMember,
            OccupancyRelationship::Owner, OccupancyRelationship::Tenant => EstateRole::Resident,
        };
    }

    public function ensureMembership(int $estateId, User $user, EstateRole $role, ?User $actor = null): EstateMembership
    {
        $membership = EstateMembership::firstOrNew(['estate_id' => $estateId, 'user_id' => $user->id, 'role' => $role->value]);
        $membership->status = MembershipStatus::Active;
        $membership->approved_by ??= $actor?->id;
        $membership->approved_at ??= now();
        $membership->save();
        $user->flushRoleCache();

        return $membership;
    }

    private function revokeCommunityRoleIfUnlinked(Occupancy $occupancy): void
    {
        $role = $this->roleFor($occupancy->relationship);
        $stillLinked = Occupancy::where('estate_id', $occupancy->estate_id)->where('user_id', $occupancy->user_id)->where('status', 'active')->get()
            ->contains(fn (Occupancy $o) => $this->roleFor($o->relationship) === $role);

        if (! $stillLinked) {
            EstateMembership::where('estate_id', $occupancy->estate_id)->where('user_id', $occupancy->user_id)->where('role', $role->value)
                ->update(['status' => MembershipStatus::Revoked->value]);
        }
    }

    public function refreshPropertyStatus(Property $property): void
    {
        $active = $property->occupancies()->where('status', 'active')->where('relationship', '!=', OccupancyRelationship::HouseholdMember->value)->pluck('relationship');

        $status = match (true) {
            $active->contains(OccupancyRelationship::Tenant) => OccupancyStatus::Tenanted,
            $active->contains(OccupancyRelationship::Owner) => OccupancyStatus::OwnerOccupied,
            $property->occupancy_status === OccupancyStatus::UnderRenovation => OccupancyStatus::UnderRenovation,
            default => OccupancyStatus::Vacant,
        };

        $property->update(['occupancy_status' => $status]);
    }
}
