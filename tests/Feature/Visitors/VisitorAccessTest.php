<?php

namespace Tests\Feature\Visitors;

use App\Enums\EstateRole;
use App\Enums\PassStatus;
use App\Enums\VisitStatus;
use App\Livewire\GateConsole;
use App\Models\Estate;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitorPass;
use App\Notifications\VisitorArrivedNotification;
use App\Notifications\WalkInApprovalNotification;
use App\Services\Estate\OccupancyService;
use App\Services\Visitors\GateService;
use App\Services\Visitors\PassService;
use App\Support\EstateContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class VisitorAccessTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    private Estate $estateModel;

    private User $host;

    private User $officer;

    private int $propertyId;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->estateModel = $this->estate();
        [$this->host, $property] = $this->resident($this->estateModel);
        $this->propertyId = $property->id;
        $this->officer = $this->staff($this->estateModel, EstateRole::SecurityOfficer);
    }

    private function issue(array $overrides = []): VisitorPass
    {
        $tz = $this->estateModel->timezone();

        return app(PassService::class)->issue($this->host, $this->estateModel, $overrides + [
            'property_id' => $this->propertyId,
            'visitor_name' => 'Kola Bankole',
            'category' => 'guest',
            'pass_type' => 'one_time',
            'valid_from' => now($tz)->subHour()->format('Y-m-d H:i'),
            'valid_until' => now($tz)->addHours(4)->format('Y-m-d H:i'),
        ]);
    }

    public function test_resident_creates_pass_with_unpredictable_token_and_hashed_code(): void
    {
        $this->inEstate($this->host, $this->estateModel)->post(route('visitors.store'), [
            'property_id' => $this->propertyId, 'visitor_name' => 'Ngozi', 'category' => 'guest', 'pass_type' => 'one_time',
            'valid_from' => now('Africa/Lagos')->format('Y-m-d\TH:i'), 'valid_until' => now('Africa/Lagos')->addHours(3)->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $pass = VisitorPass::firstOrFail();
        $this->assertSame(43, strlen($pass->token()));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pass->code());
        $raw = \DB::table('visitor_passes')->first();
        $this->assertStringNotContainsString($pass->token(), json_encode($raw));
        $this->assertSame(1, $pass->max_entries);
    }

    public function test_resident_cannot_issue_pass_for_unlinked_property(): void
    {
        [, $other] = $this->resident($this->estateModel);

        $this->expectException(ValidationException::class);
        $this->issue(['property_id' => $other->id]);
    }

    public function test_officer_verifies_and_admits_once_only(): void
    {
        $pass = $this->issue();
        $gate = app(GateService::class);

        $check = $gate->check($this->estateModel, $this->officer, $pass->code());
        $this->assertTrue($check->valid);

        $visit = $gate->admit($pass, $this->officer);
        $this->assertSame(VisitStatus::Inside, $visit->status);
        $this->assertSame(PassStatus::Consumed, $pass->fresh()->status);
        Notification::assertSentTo($this->host, VisitorArrivedNotification::class);

        // Second scan of the same QR is refused.
        $again = $gate->check($this->estateModel, $this->officer, PassService::qrPayload($pass));
        $this->assertFalse($again->valid);
        $this->assertSame('consumed', $again->result);

        $this->expectException(ValidationException::class);
        $gate->admit($pass, $this->officer);
    }

    public function test_simultaneous_admits_with_stale_state_cannot_both_succeed(): void
    {
        $pass = $this->issue();
        $staleCopyA = VisitorPass::find($pass->id);
        $staleCopyB = VisitorPass::find($pass->id);
        $gate = app(GateService::class);

        $gate->admit($staleCopyA, $this->officer);

        try {
            $gate->admit($staleCopyB, $this->officer);
            $this->fail('Second admit should have been rejected.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(1, Visit::where('visitor_pass_id', $pass->id)->count());
        $this->assertSame(1, $pass->fresh()->entries_count);
    }

    public function test_multi_entry_pass_respects_entry_limit(): void
    {
        $pass = $this->issue(['pass_type' => 'multi_entry', 'max_entries' => 2]);
        $gate = app(GateService::class);

        $gate->admit($pass, $this->officer);
        $gate->admit($pass->fresh(), $this->officer);

        $this->assertSame(PassStatus::Consumed, $pass->fresh()->status);
        $this->assertFalse($gate->check($this->estateModel, $this->officer, PassService::qrPayload($pass))->valid);
    }

    public function test_revoked_expired_and_future_passes_are_rejected(): void
    {
        $gate = app(GateService::class);

        $revoked = $this->issue();
        app(PassService::class)->revoke($revoked, $this->host);
        $this->assertSame('revoked', $gate->check($this->estateModel, $this->officer, PassService::qrPayload($revoked))->result);
        $this->assertSame('not_found', $gate->check($this->estateModel, $this->officer, $revoked->code())->result, 'Revoked codes are freed and no longer resolve');

        $future = $this->issue(['valid_from' => now('Africa/Lagos')->addDay()->format('Y-m-d H:i'), 'valid_until' => now('Africa/Lagos')->addDays(2)->format('Y-m-d H:i')]);
        $this->assertSame('not_yet_valid', $gate->check($this->estateModel, $this->officer, $future->code())->result);

        $expiring = $this->issue();
        $this->travel(6)->hours();
        $this->assertSame('expired', $gate->check($this->estateModel, $this->officer, PassService::qrPayload($expiring))->result);
    }

    public function test_recurring_pass_only_valid_in_its_schedule(): void
    {
        $this->travelTo(now('Africa/Lagos')->next('Monday')->setTime(10, 0));
        $pass = $this->issue(['pass_type' => 'recurring', 'days' => [1, 2, 3], 'start_time' => '08:00', 'end_time' => '17:00',
            'valid_from' => now('Africa/Lagos')->format('Y-m-d H:i'), 'valid_until' => now('Africa/Lagos')->addDays(14)->format('Y-m-d H:i')]);
        $gate = app(GateService::class);

        $this->assertTrue($gate->check($this->estateModel, $this->officer, $pass->code())->valid);

        $this->travelTo(now('Africa/Lagos')->setTime(19, 0));
        $this->assertSame('outside_schedule', $gate->check($this->estateModel, $this->officer, $pass->code())->result);

        $this->travelTo(now('Africa/Lagos')->next('Saturday')->setTime(10, 0));
        $this->assertSame('outside_schedule', $gate->check($this->estateModel, $this->officer, $pass->code())->result);
    }

    public function test_pass_from_another_estate_is_not_found(): void
    {
        $pass = $this->issue();
        $otherEstate = $this->estate();
        $otherOfficer = $this->staff($otherEstate, EstateRole::SecurityOfficer);

        $check = app(GateService::class)->check($otherEstate, $otherOfficer, PassService::qrPayload($pass));

        $this->assertFalse($check->valid);
        $this->assertSame('not_found', $check->result);
    }

    public function test_pass_is_refused_once_host_moves_out(): void
    {
        $pass = $this->issue();
        $occupancy = $this->host->occupancies()->first();
        app(OccupancyService::class)->end($occupancy);

        $this->assertSame('host_inactive', app(GateService::class)->check($this->estateModel, $this->officer, $pass->code())->result);
    }

    public function test_residents_cannot_use_gate_operations(): void
    {
        $pass = $this->issue();

        $this->inEstate($this->host, $this->estateModel)->get(route('gate.index'))->assertForbidden();

        $this->expectException(HttpException::class);
        app(GateService::class)->admit($pass, $this->host);
    }

    public function test_gate_console_checks_and_admits_through_livewire(): void
    {
        $pass = $this->issue();
        $this->actingAs($this->officer)->withSession(['estate_id' => $this->estateModel->id]);
        app(EstateContext::class)->set($this->officer, $this->estateModel);

        Livewire::test(GateConsole::class)
            ->set('code', $pass->code())
            ->call('check')
            ->assertSet('valid', true)
            ->assertSee('Kola Bankole')
            ->call('admit')
            ->assertSet('admitted', 'Kola Bankole');

        Livewire::test(GateConsole::class)
            ->call('scanned', PassService::qrPayload($pass))
            ->assertSet('valid', false)
            ->assertSet('result', 'consumed')
            ->set('denyReason', 'Pass already used')
            ->call('deny');

        $this->assertDatabaseHas('visits', ['status' => 'denied', 'denial_reason' => 'Pass already used']);
        $this->assertDatabaseHas('pass_scans', ['visitor_pass_id' => $pass->id, 'result' => 'admitted']);
    }

    public function test_walk_in_requires_host_approval(): void
    {
        $visit = app(GateService::class)->registerWalkIn($this->estateModel, $this->officer, ['property_id' => $this->propertyId, 'visitor_name' => 'Emmanuel', 'category' => 'guest']);

        $this->assertSame(VisitStatus::AwaitingHost, $visit->status);
        Notification::assertSentTo($this->host, WalkInApprovalNotification::class);

        [$stranger] = $this->resident($this->estateModel);
        $this->inEstate($stranger, $this->estateModel)->post(route('visitors.walk-ins.respond', $visit), ['decision' => 'approve'])->assertForbidden();

        $this->inEstate($this->host, $this->estateModel)->post(route('visitors.walk-ins.respond', $visit), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame(VisitStatus::Inside, $visit->fresh()->status);

        app(GateService::class)->checkOut($visit->fresh(), $this->officer);
        $this->assertSame(VisitStatus::Exited, $visit->fresh()->status);
    }

    public function test_public_pass_link_shows_code_without_caching(): void
    {
        $pass = $this->issue();

        $this->get(route('passes.public', $pass->token()))
            ->assertOk()
            ->assertSee($pass->formattedCode())
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->get(route('passes.public', 'not-a-real-token'))->assertNotFound();
    }

    public function test_host_can_view_and_revoke_own_pass_but_neighbour_cannot(): void
    {
        $pass = $this->issue();
        [$neighbour] = $this->resident($this->estateModel);

        $this->inEstate($neighbour, $this->estateModel)->get(route('visitors.show', $pass))->assertForbidden();
        $this->inEstate($neighbour, $this->estateModel)->post(route('visitors.revoke', $pass))->assertForbidden();

        $this->inEstate($this->host, $this->estateModel)->get(route('visitors.show', $pass))->assertOk()->assertSee($pass->formattedCode());
        $this->inEstate($this->host, $this->estateModel)->get(route('visitors.qr', $pass))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->inEstate($this->host, $this->estateModel)->post(route('visitors.revoke', $pass))->assertRedirect();
        $this->assertSame(PassStatus::Revoked, $pass->fresh()->status);
    }
}
