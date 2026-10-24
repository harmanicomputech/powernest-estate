<?php

namespace Tests\Feature\Power;

use App\Enums\EstateRole;
use App\Enums\PowerPurchaseStatus as Status;
use App\Models\PowerPurchase;
use App\Notifications\PowerTokenIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class OfficeVendTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    public function test_manager_issues_token_without_payment_and_owner_is_emailed(): void
    {
        $this->enableIntegrations();
        Notification::fake();
        $estate = $this->estate();
        $manager = $this->staff($estate, EstateRole::EstateManager);
        [$resident, $property] = $this->resident($estate);
        $meter = $this->meterFor($property);

        Http::fake(['stron.test/api/VendingMeter' => Http::response([[
            'Customer_name' => 'Ada', 'Meter_id' => $meter->meter_number, 'Total_unit' => '44.4', 'Unit' => 'kWh', 'Token' => '11112222333344445555', 'Gen_time' => '2026-10-24 10:00:00',
        ]])]);

        $this->inEstate($manager, $estate)->get(route('manage.office-vend.create', ['meter' => $meter->meter_number]))
            ->assertOk()->assertSee($resident->name);

        $this->inEstate($manager, $estate)->post(route('manage.office-vend.store'), ['meter_id' => $meter->id, 'amount' => '10,000', 'note' => 'Paid cash at the office'])
            ->assertRedirect();

        $purchase = PowerPurchase::firstOrFail();
        $this->assertSame(Status::TokenIssued, $purchase->status);
        $this->assertSame('office', $purchase->gateway);
        $this->assertSame(1_000_000, $purchase->vend_amount);
        $this->assertSame(0, $purchase->service_charge);
        $this->assertSame(0, $purchase->amount_paid);
        $this->assertSame($resident->id, $purchase->user_id);
        $this->assertSame($manager->id, $purchase->issued_by);
        Notification::assertSentTo($resident, PowerTokenIssued::class);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'VendingMeter') && (string) $request['Amount'] === '10000');

        $this->inEstate($manager, $estate)->get(route('manage.office-vend.show', $purchase))->assertOk()->assertSee('1111 2222 3333 4444 5555');
        $this->inEstate($manager, $estate)->get(route('pdf.purchase', $purchase))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->inEstate($resident, $estate)->get(route('power.purchases.show', $purchase))->assertOk();
    }

    public function test_residents_and_officers_cannot_issue_free_tokens(): void
    {
        $estate = $this->estate();
        [$resident] = $this->resident($estate);
        $officer = $this->staff($estate, EstateRole::SecurityOfficer);

        $this->inEstate($resident, $estate)->get(route('manage.office-vend.create'))->assertForbidden();
        $this->inEstate($officer, $estate)->post(route('manage.office-vend.store'), ['meter_id' => 1, 'amount' => '1000', 'note' => 'x'])->assertForbidden();
    }
}
