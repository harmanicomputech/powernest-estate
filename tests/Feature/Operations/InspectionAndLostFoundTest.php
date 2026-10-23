<?php

namespace Tests\Feature\Operations;

use App\Enums\EstateRole;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\LostFoundItem;
use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class InspectionAndLostFoundTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_supervisor_creates_checklist_and_officer_completes_it_with_follow_up(): void
    {
        $estate = $this->estate();
        $supervisor = $this->staff($estate, EstateRole::SecuritySupervisor);
        $officer = $this->staff($estate, EstateRole::SecurityOfficer);

        $this->inEstate($officer, $estate)->post(route('inspections.templates.store'), ['name' => 'X', 'category' => 'security', 'items' => 'A'])->assertForbidden();
        $this->inEstate($supervisor, $estate)->post(route('inspections.templates.store'), ['name' => 'Night patrol', 'category' => 'security', 'items' => "Perimeter lights\nFence intact\n\nGate barrier"])->assertRedirect();

        $template = InspectionTemplate::firstOrFail();
        $this->assertSame(['Perimeter lights', 'Fence intact', 'Gate barrier'], $template->items);

        $this->inEstate($officer, $estate)->post(route('inspections.start', $template), ['location' => 'North fence'])->assertRedirect();
        $inspection = Inspection::firstOrFail();
        $this->inEstate($officer, $estate)->get(route('inspections.show', $inspection))->assertOk()->assertSee('Fence intact');

        // Every item needs a result.
        $this->inEstate($officer, $estate)->post(route('inspections.complete', $inspection), ['answers' => [['result' => 'pass']]])->assertSessionHasErrors('answers.1.result');

        $this->inEstate($officer, $estate)->post(route('inspections.complete', $inspection), [
            'answers' => [['result' => 'pass'], ['result' => 'fail', 'note' => 'Hole near Palm Close'], ['result' => 'na']],
            'raise_maintenance' => '1',
        ])->assertRedirect();

        $inspection->refresh();
        $this->assertTrue($inspection->isComplete());
        $this->assertSame(1, $inspection->failed_count);
        $request = MaintenanceRequest::firstOrFail();
        $this->assertStringContainsString('Hole near Palm Close', $request->description);

        // Completed inspections are immutable.
        $this->inEstate($officer, $estate)->post(route('inspections.complete', $inspection), ['answers' => [['result' => 'pass'], ['result' => 'pass'], ['result' => 'pass']]])->assertSessionHasErrors('inspection');
    }

    public function test_residents_cannot_perform_inspections(): void
    {
        $estate = $this->estate();
        [$resident] = $this->resident($estate);

        $this->inEstate($resident, $estate)->get(route('inspections.index'))->assertForbidden();
    }

    public function test_lost_and_found_post_and_resolve(): void
    {
        $estate = $this->estate();
        [$alice] = $this->resident($estate);
        [$bob] = $this->resident($estate);
        $supervisor = $this->staff($estate, EstateRole::SecuritySupervisor);

        $this->inEstate($alice, $estate)->post(route('lost-found.store'), ['type' => 'lost', 'title' => 'Blue umbrella', 'location' => 'Clubhouse'])->assertRedirect();
        $item = LostFoundItem::firstOrFail();

        $this->inEstate($bob, $estate)->get(route('lost-found.index'))->assertOk()->assertSee('Blue umbrella')->assertDontSee($alice->name);
        $this->inEstate($bob, $estate)->post(route('lost-found.resolve', $item))->assertForbidden();
        $this->inEstate($supervisor, $estate)->get(route('lost-found.index'))->assertSee($alice->name);
        $this->inEstate($alice, $estate)->post(route('lost-found.resolve', $item))->assertRedirect();

        $this->assertSame('resolved', $item->fresh()->status);
    }
}
