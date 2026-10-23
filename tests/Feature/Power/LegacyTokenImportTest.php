<?php

namespace Tests\Feature\Power;

use App\Enums\PowerPurchaseStatus as Status;
use App\Models\PowerPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class LegacyTokenImportTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    public function test_staged_legacy_tokens_are_encrypted_into_power_tokens(): void
    {
        $estate = $this->estate();
        [$resident, $property] = $this->resident($estate);
        $meter = $this->meterFor($property);

        $purchase = PowerPurchase::create([
            'reference' => 'LGC000123', 'estate_id' => $estate->id, 'user_id' => $resident->id, 'meter_id' => $meter->id,
            'property_id' => $property->id, 'meter_number' => $meter->meter_number, 'gateway' => 'paystack',
            'vend_amount' => 500000, 'service_charge' => 25000, 'total_amount' => 525000, 'amount_paid' => 525000,
            'status' => Status::TokenIssued->value, 'paid_at' => now(), 'token_issued_at' => now(),
        ]);

        DB::table('legacy_token_imports')->insert([
            ['purchase_reference' => 'LGC000123', 'token' => '7017 0803 6713 4864 8463', 'units' => 22.7, 'meter_number' => $meter->meter_number, 'meta' => '{"Gen_time":"2025.05.25"}', 'issued_at' => now()],
            ['purchase_reference' => 'LGC999999', 'token' => '1111', 'units' => 1, 'meter_number' => '0', 'meta' => null, 'issued_at' => now()],
        ]);

        $this->artisan('power:import-legacy-tokens')->assertSuccessful();

        $token = $purchase->fresh()->token;
        $this->assertSame('70170803671348648463', $token->token);
        $this->assertSame('legacy', $token->source);
        $this->assertNotSame('70170803671348648463', DB::table('power_tokens')->value('token'), 'Token must be stored encrypted');
        $this->assertSame(0, DB::table('legacy_token_imports')->count());
    }
}
