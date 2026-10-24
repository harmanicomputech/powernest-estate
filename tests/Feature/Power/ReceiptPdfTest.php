<?php

namespace Tests\Feature\Power;

use App\Enums\PowerPurchaseStatus as Status;
use App\Models\PowerPurchase;
use App\Models\PowerToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class ReceiptPdfTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    public function test_resident_downloads_power_receipt_pdf(): void
    {
        $estate = $this->estate();
        [$resident, $property] = $this->resident($estate);
        $meter = $this->meterFor($property);

        $purchase = PowerPurchase::create([
            'reference' => 'PNE-6H458J5AKVU3', 'estate_id' => $estate->id, 'user_id' => $resident->id, 'meter_id' => $meter->id,
            'property_id' => $property->id, 'meter_number' => $meter->meter_number, 'gateway' => 'payaza', 'payment_channel' => 'card',
            'vend_amount' => 500000, 'service_charge' => 35000, 'total_amount' => 535000, 'amount_paid' => 535000,
            'status' => Status::TokenIssued->value, 'paid_at' => now(), 'token_issued_at' => now(),
        ]);
        PowerToken::create(['power_purchase_id' => $purchase->id, 'token' => '70170803671348648463', 'units' => '22.70', 'meter_number' => $meter->meter_number, 'issued_at' => now()]);

        // Shared-hosting layout: the web root (public_html) lives outside the application folder.
        $webRoot = sys_get_temp_dir().'/pne-public-html-'.uniqid();
        mkdir($webRoot);
        $this->app->usePublicPath($webRoot);
        config(['dompdf.public_path' => $webRoot]);

        $response = $this->inEstate($resident, $estate)->get(route('pdf.purchase', $purchase));
        rmdir($webRoot);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
