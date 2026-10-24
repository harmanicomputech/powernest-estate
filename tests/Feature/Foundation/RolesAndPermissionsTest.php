<?php

namespace Tests\Feature\Foundation;

use App\Enums\EstateRole;
use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Models\EstateMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    public function test_owner_customises_a_role_and_it_takes_effect(): void
    {
        $estate = $this->estate();
        $owner = $this->staff($estate, EstateRole::EstateOwner);
        $officer = $this->staff($estate, EstateRole::SecurityOfficer);

        $this->inEstate($officer, $estate)->get(route('manage.visits.index'))->assertForbidden();
        $this->inEstate($owner, $estate)->get(route('manage.roles.index'))->assertOk()->assertSee('Security Officer');
        $this->inEstate($owner, $estate)->get(route('manage.roles.edit', EstateRole::SecurityOfficer))->assertOk()->assertSee('View the visitor log');

        $this->inEstate($owner, $estate)->put(route('manage.roles.update', EstateRole::SecurityOfficer), [
            'permissions' => [Permission::GateOperate->value, Permission::VisitorsViewAll->value],
        ])->assertRedirect(route('manage.roles.index'));

        $officer->flushRoleCache();
        $this->inEstate($officer, $estate->fresh())->get(route('manage.visits.index'))->assertOk();
        $this->assertFalse($officer->hasEstatePermission($estate->fresh(), Permission::IncidentsReport), 'Unticked permissions are removed');

        $this->inEstate($owner, $estate)->delete(route('manage.roles.reset', EstateRole::SecurityOfficer))->assertRedirect();
        $this->assertTrue($officer->hasEstatePermission($estate->fresh(), Permission::IncidentsReport));
    }

    public function test_owner_role_cannot_be_reduced_and_only_role_managers_see_the_page(): void
    {
        $estate = $this->estate();
        $owner = $this->staff($estate, EstateRole::EstateOwner);
        $admin = $this->staff($estate, EstateRole::EstateAdmin);

        $this->inEstate($owner, $estate)->put(route('manage.roles.update', EstateRole::EstateOwner), ['permissions' => []])->assertForbidden();
        $this->inEstate($admin, $estate)->get(route('manage.roles.index'))->assertForbidden();
    }

    public function test_existing_resident_can_be_given_a_staff_role_then_changed_and_removed(): void
    {
        Notification::fake();
        $estate = $this->estate();
        $owner = $this->staff($estate, EstateRole::EstateOwner);
        [$resident] = $this->resident($estate);

        $this->inEstate($owner, $estate)->post(route('manage.members.invite'), ['email' => strtoupper($resident->email), 'role' => EstateRole::EstateAdmin->value])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $resident->flushRoleCache();
        $this->assertTrue($resident->hasRoleIn($estate, EstateRole::EstateAdmin));
        $this->assertTrue($resident->hasRoleIn($estate, EstateRole::Resident), 'Resident role is kept');

        $membership = EstateMembership::where('user_id', $resident->id)->where('role', EstateRole::EstateAdmin->value)->firstOrFail();
        $this->inEstate($owner, $estate)->put(route('manage.members.role', $membership), ['role' => EstateRole::FinanceOfficer->value])->assertRedirect();
        $this->assertSame(EstateRole::FinanceOfficer, $membership->fresh()->role);

        $this->inEstate($owner, $estate)->post(route('manage.members.status', $membership), ['status' => 'revoked'])->assertRedirect();
        $this->assertSame(MembershipStatus::Revoked, $membership->fresh()->status);

        $ownerMembership = EstateMembership::where('user_id', $owner->id)->firstOrFail();
        $this->inEstate($owner, $estate)->put(route('manage.members.role', $ownerMembership), ['role' => EstateRole::Technician->value])->assertForbidden();
    }
}
