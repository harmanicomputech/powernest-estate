<?php

namespace Tests\Feature\Operations;

use App\Enums\BookingStatus;
use App\Enums\EstateRole;
use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\Priority;
use App\Models\Facility;
use App\Models\MaintenanceRequest;
use App\Services\Operations\BookingService;
use App\Services\Operations\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class MaintenanceAndBookingTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
    }

    public function test_full_maintenance_lifecycle_with_valid_transitions(): void
    {
        $estate = $this->estate();
        [$resident, $property] = $this->resident($estate);
        $facility = $this->staff($estate, EstateRole::FacilityManager);
        $tech = $this->staff($estate, EstateRole::Technician);

        $this->inEstate($resident, $estate)->post(route('maintenance.store'), [
            'property_id' => $property->id, 'category' => 'plumbing', 'title' => 'Leaking tap', 'description' => 'Drips all night', 'priority' => 'high',
            'photos' => [UploadedFile::fake()->image('leak.jpg')],
        ])->assertRedirect();

        $request = MaintenanceRequest::firstOrFail();
        $this->assertSame(MaintenanceStatus::Submitted, $request->status);
        $this->assertCount(1, $request->documents);
        Storage::disk('local')->assertExists($request->documents->first()->path);

        // Residents cannot assign; technicians cannot see others' work until assigned.
        $this->inEstate($resident, $estate)->post(route('maintenance.assign', $request), ['assigned_to' => $tech->id, 'priority' => 'high'])->assertForbidden();
        $this->inEstate($tech, $estate)->get(route('maintenance.show', $request))->assertForbidden();

        $this->inEstate($facility, $estate)->post(route('maintenance.assign', $request), ['assigned_to' => $tech->id, 'priority' => 'urgent'])->assertRedirect();
        $this->assertSame(MaintenanceStatus::Assigned, $request->fresh()->status);

        $this->inEstate($tech, $estate)->post(route('maintenance.status', $request), ['status' => 'in_progress'])->assertRedirect();
        $this->inEstate($tech, $estate)->post(route('maintenance.comment', $request), ['body' => 'Washer replaced', 'internal' => '1'])->assertRedirect();
        $this->inEstate($tech, $estate)->post(route('maintenance.status', $request), ['status' => 'completed'])->assertRedirect();

        // Internal notes are hidden from the resident.
        $this->inEstate($resident, $estate)->get(route('maintenance.show', $request))->assertOk()->assertDontSee('Washer replaced')->assertSee('Is the problem fixed?');

        $this->inEstate($resident, $estate)->post(route('maintenance.confirm', $request), ['resolved' => '1'])->assertRedirect();
        $this->assertSame(MaintenanceStatus::Closed, $request->fresh()->status);

        // Closed is terminal.
        $this->expectException(ValidationException::class);
        app(MaintenanceService::class)->transition($request->fresh(), MaintenanceStatus::InProgress, $facility);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $estate = $this->estate();
        [$resident, $property] = $this->resident($estate);
        $facility = $this->staff($estate, EstateRole::FacilityManager);
        $request = app(MaintenanceService::class)->submit($resident, $estate, ['property_id' => $property->id, 'category' => 'electrical', 'title' => 'x', 'description' => 'y', 'priority' => Priority::Low->value]);

        $this->expectException(ValidationException::class);
        app(MaintenanceService::class)->transition($request, MaintenanceStatus::Completed, $facility);
    }

    public function test_bookings_cannot_overlap_and_respect_hours(): void
    {
        $estate = $this->estate();
        [$alice] = $this->resident($estate);
        [$bob] = $this->resident($estate);
        $court = Facility::create(['estate_id' => $estate->id, 'name' => 'Court', 'requires_approval' => false, 'opens_at' => '06:00', 'closes_at' => '21:00', 'max_duration_minutes' => 120]);
        $service = app(BookingService::class);
        $day = Carbon::now('Africa/Lagos')->addDays(2)->setTime(10, 0);

        $booking = $service->book($alice, $court, $day->copy()->utc(), $day->copy()->addHour()->utc(), 2, null);
        $this->assertSame(BookingStatus::Approved, $booking->status);

        try {
            $service->book($bob, $court, $day->copy()->addMinutes(30)->utc(), $day->copy()->addMinutes(90)->utc(), 2, null);
            $this->fail('Overlapping booking accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('overlaps', $e->getMessage());
        }

        $this->expectException(ValidationException::class);
        $service->book($bob, $court, $day->copy()->setTime(20, 30)->utc(), $day->copy()->setTime(22, 0)->utc(), 2, null);
    }

    public function test_booking_fee_is_invoiced_on_approval_and_voided_on_cancel(): void
    {
        $estate = $this->estate();
        [$resident, $property] = $this->resident($estate);
        $facilityManager = $this->staff($estate, EstateRole::FacilityManager);
        $hall = Facility::create(['estate_id' => $estate->id, 'name' => 'Hall', 'requires_approval' => true, 'fee' => 2_500_000, 'opens_at' => '08:00', 'closes_at' => '22:00', 'max_duration_minutes' => 360]);
        $day = Carbon::now('Africa/Lagos')->addDays(5)->setTime(14, 0);

        $booking = app(BookingService::class)->book($resident, $hall, $day->copy()->utc(), $day->copy()->addHours(3)->utc(), 30, 'Party');
        $this->assertNull($booking->invoice_id);

        $this->inEstate($facilityManager, $estate)->post(route('manage.bookings.review', $booking), ['decision' => 'approve'])->assertRedirect();
        $invoice = $booking->fresh()->invoice;
        $this->assertNotNull($invoice);
        $this->assertSame(2_500_000, $invoice->total);
        $this->assertSame($property->id, $invoice->property_id);

        $this->inEstate($resident, $estate)->get(route('facilities.bookings.index'))->assertSee('Pay');
        $this->inEstate($resident, $estate)->post(route('bookings.cancel', $booking))->assertRedirect();
        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
    }
}
