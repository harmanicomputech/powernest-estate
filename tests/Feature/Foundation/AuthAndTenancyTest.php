<?php

namespace Tests\Feature\Foundation;

use App\Enums\EstateRole;
use App\Enums\MembershipStatus;
use App\Enums\OccupancyRelationship;
use App\Models\EstateInvitation;
use App\Models\MembershipRequest;
use App\Models\Property;
use App\Models\User;
use App\Notifications\EstateInvitationNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Estate\MembershipService;
use App\Services\Estate\OccupancyService;
use App\Services\Operations\IncidentService;
use App\Services\Operations\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class AuthAndTenancyTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    public function test_registration_creates_an_active_user_who_must_verify_email(): void
    {
        $this->post('/register', ['name' => 'Ada Obi', 'email' => 'Ada@Example.com', 'phone' => '08030000000', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1'])
            ->assertRedirect('/app');

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isPlatformAdmin());
        $this->get('/app')->assertRedirect(route('verification.notice'));
    }

    public function test_registration_cannot_mass_assign_privileges(): void
    {
        $this->post('/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1', 'is_platform_admin' => '1', 'status' => 'active']);

        $this->assertFalse(User::where('email', 'eve@example.com')->firstOrFail()->isPlatformAdmin());
    }

    public function test_login_is_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertStatus(429);
    }

    public function test_suspended_user_cannot_sign_in_and_is_logged_out(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $active = User::factory()->create();
        $this->actingAs($active);
        $active->forceFill(['status' => 'suspended'])->save();
        $this->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_without_membership_goes_to_onboarding(): void
    {
        $this->actingAs(User::factory()->create())->get('/app')->assertRedirect(route('onboarding'));
    }

    public function test_session_estate_id_for_a_foreign_estate_is_ignored(): void
    {
        $mine = $this->estate();
        $theirs = $this->estate();
        [$resident] = $this->resident($mine);

        $this->actingAs($resident)->withSession(['estate_id' => $theirs->id])->get('/app')->assertOk()->assertSee($mine->name)->assertDontSee($theirs->name);
        $this->actingAs($resident)->post(route('estates.switch', $theirs))->assertForbidden();
    }

    public function test_estate_scoped_records_from_other_estates_resolve_to_404(): void
    {
        $mine = $this->estate();
        $theirs = $this->estate();
        $manager = $this->staff($mine, EstateRole::EstateManager);
        [$foreignResident, $foreignProperty] = $this->resident($theirs);

        $this->inEstate($manager, $mine)->get(route('manage.properties.show', $foreignProperty))->assertNotFound();

        $request = app(MaintenanceService::class)->submit($foreignResident, $theirs, ['property_id' => $foreignProperty->id, 'category' => 'plumbing', 'title' => 'Leak', 'description' => 'Leak']);
        $this->inEstate($manager, $mine)->get(route('maintenance.show', $request))->assertNotFound();
    }

    public function test_roles_differ_per_estate(): void
    {
        $a = $this->estate();
        $b = $this->estate();
        $user = $this->staff($a, EstateRole::EstateManager);
        $property = Property::factory()->create(['estate_id' => $b->id]);
        app(OccupancyService::class)->addOccupant($property, $user, OccupancyRelationship::Owner);

        $this->inEstate($user, $a)->get(route('manage.billing.index'))->assertOk();
        $this->inEstate($user, $b)->get(route('manage.billing.index'))->assertForbidden();
        $this->inEstate($user, $b)->get(route('power.index'))->assertOk();
    }

    public function test_platform_admin_has_no_implicit_estate_access(): void
    {
        $estate = $this->estate();
        $admin = User::factory()->platformAdmin()->create();
        [, $property] = $this->resident($estate);

        $this->actingAs($admin)->get(route('platform.dashboard'))->assertOk();
        $this->actingAs($admin)->withSession(['estate_id' => $estate->id])->get(route('manage.properties.show', $property))->assertRedirect(route('onboarding'));

        $resident = User::factory()->create();
        $this->actingAs($resident)->get(route('platform.dashboard'))->assertForbidden();
    }

    public function test_suspended_membership_loses_access(): void
    {
        $estate = $this->estate();
        $manager = $this->staff($estate, EstateRole::EstateManager);
        $guard = $this->staff($estate, EstateRole::SecurityOfficer);

        $membership = $guard->memberships()->first();
        app(MembershipService::class)->setStatus($membership, MembershipStatus::Suspended, $manager);

        $this->inEstate($guard->fresh(), $estate)->get(route('gate.index'))->assertRedirect(route('onboarding'));
    }

    public function test_invitation_flow_links_resident_to_property(): void
    {
        Notification::fake();
        $estate = $this->estate();
        $manager = $this->staff($estate, EstateRole::EstateManager);
        $property = Property::factory()->create(['estate_id' => $estate->id]);

        $this->inEstate($manager, $estate)->post(route('manage.properties.occupants.store', $property), ['email' => 'new.tenant@example.com', 'relationship' => 'tenant'])->assertRedirect();

        $token = null;
        Notification::assertSentOnDemand(EstateInvitationNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);
        $this->assertDatabaseMissing('estate_invitations', ['token_hash' => $token]);

        $invitee = User::factory()->create(['email' => 'new.tenant@example.com']);
        $wrongUser = User::factory()->create();

        $this->actingAs($wrongUser)->post(route('invitations.accept', $token))->assertSessionHasErrors('invitation');

        $this->actingAs($invitee)->post(route('invitations.accept', $token))->assertRedirect(route('dashboard'));
        $this->assertTrue($invitee->fresh()->hasRoleIn($estate, EstateRole::Resident));
        $this->assertTrue($invitee->fresh()->authorizedProperties($estate)->whereKey($property->id)->exists());

        // Single use.
        $this->actingAs($invitee)->post(route('invitations.accept', $token))->assertSessionHasErrors('invitation');
        $this->assertNotNull(EstateInvitation::first()->accepted_at);
    }

    public function test_access_request_requires_matching_code_and_property(): void
    {
        Notification::fake();
        $estate = $this->estate();
        $manager = $this->staff($estate, EstateRole::EstateManager);
        $property = Property::factory()->create(['estate_id' => $estate->id, 'identifier' => 'A-101']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.request'), ['estate_code' => 'WRONG', 'property_identifier' => 'A-101', 'relationship' => 'tenant'])->assertSessionHasErrors('estate_code');
        $this->actingAs($user)->post(route('onboarding.request'), ['estate_code' => strtolower($estate->code), 'property_identifier' => 'a-101', 'relationship' => 'tenant'])->assertSessionHasNoErrors();

        $request = MembershipRequest::firstOrFail();
        $this->inEstate($manager, $estate)->post(route('manage.requests.review', $request), ['decision' => 'approve'])->assertRedirect();

        $this->assertTrue($user->fresh()->authorizedProperties($estate)->whereKey($property->id)->exists());
    }

    public function test_incident_records_are_restricted(): void
    {
        $estate = $this->estate();
        [$reporter] = $this->resident($estate);
        [$neighbour] = $this->resident($estate);
        $supervisor = $this->staff($estate, EstateRole::SecuritySupervisor);

        $incident = app(IncidentService::class)->report($reporter, $estate, ['category' => 'theft', 'severity' => 'medium', 'title' => 'Bike stolen', 'description' => 'From the porch']);

        $this->inEstate($neighbour, $estate)->get(route('incidents.show', $incident))->assertForbidden();
        $this->inEstate($neighbour, $estate)->get(route('incidents.index'))->assertOk()->assertDontSee('Bike stolen');
        $this->inEstate($reporter, $estate)->get(route('incidents.show', $incident))->assertOk();
        $this->inEstate($supervisor, $estate)->get(route('incidents.show', $incident))->assertOk();
    }

    public function test_audit_log_is_append_only(): void
    {
        $estate = $this->estate();
        $log = app(AuditLogger::class)->record('test.event', null, ['password' => 'hunter2', 'note' => 'ok'], $estate);

        $this->assertSame('[REDACTED]', $log->properties['password']);
        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_private_pages_are_not_cacheable(): void
    {
        $estate = $this->estate();
        [$resident] = $this->resident($estate);

        $response = $this->inEstate($resident, $estate)->get('/app')->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }
}
