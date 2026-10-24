<?php

namespace Tests\Feature\Operations;

use App\Enums\EstateRole;
use App\Enums\PowerPurchaseStatus as Status;
use App\Models\ChargeType;
use App\Models\PowerPurchase;
use App\Models\PowerToken;
use App\Services\Billing\BillingService;
use App\Services\Reports\ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class ReportDownloadTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    public function test_manager_downloads_every_report_as_csv_and_pdf(): void
    {
        Notification::fake();
        $estate = $this->estate();
        $manager = $this->staff($estate, EstateRole::EstateManager);
        [$resident, $property] = $this->resident($estate);
        $meter = $this->meterFor($property);

        foreach ([['paystack', 500_000, 35_000, 535_000, null], ['office', 1_000_000, 0, 0, $manager->id]] as $i => [$gateway, $vend, $fee, $paid, $issuer]) {
            $purchase = PowerPurchase::create([
                'reference' => 'PNE-REPORT'.$i, 'estate_id' => $estate->id, 'user_id' => $resident->id, 'issued_by' => $issuer, 'meter_id' => $meter->id,
                'property_id' => $property->id, 'meter_number' => $meter->meter_number, 'gateway' => $gateway, 'vend_amount' => $vend,
                'service_charge' => $fee, 'total_amount' => $vend + $fee, 'amount_paid' => $paid, 'status' => Status::TokenIssued->value,
                'paid_at' => now(), 'token_issued_at' => now(),
            ]);
            PowerToken::create(['power_purchase_id' => $purchase->id, 'token' => '1234567890123456789'.$i, 'units' => '20.00', 'meter_number' => $meter->meter_number, 'issued_at' => now()]);
        }

        $charge = ChargeType::create(['estate_id' => $estate->id, 'name' => 'Estate management & security levy', 'code' => 'LEVY', 'amount' => 100_000_000, 'frequency' => 'annual']);
        $year = now('Africa/Lagos')->startOfYear();
        app(BillingService::class)->runCharge($charge, $year, $year->copy()->endOfYear(), now()->addMonth(), $manager);

        $range = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()];

        $csv = $this->inEstate($manager, $estate)->get(route('manage.reports.download', $range + ['type' => 'power', 'format' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $csv->streamedContent();
        $this->assertStringContainsString('"Tokens issued",2', $content);
        $this->assertStringContainsString('Estate office', $content);
        $this->assertStringContainsString('"Issued by office (no payment)","1 · ₦10,000.00"', $content);

        foreach (array_keys(ReportBuilder::TYPES) as $type) {
            $this->inEstate($manager, $estate)->get(route('manage.reports.download', $range + ['type' => $type, 'format' => 'csv']))->assertOk();
        }

        $levy = $this->inEstate($manager, $estate)->get(route('manage.reports.download', ['from' => $year->toDateString(), 'to' => now()->toDateString(), 'type' => 'levy', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('1000000.00', $levy);

        foreach (['power', 'levy'] as $type) {
            $pdf = $this->inEstate($manager, $estate)->get(route('manage.reports.download', $range + ['type' => $type, 'format' => 'pdf']));
            $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        }

        $this->inEstate($resident, $estate)->get(route('manage.reports.download', $range + ['type' => 'power', 'format' => 'csv']))->assertForbidden();
    }
}
