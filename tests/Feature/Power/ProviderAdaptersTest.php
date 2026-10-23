<?php

namespace Tests\Feature\Power;

use App\Integrations\IntegrationException;
use App\Integrations\Payments\PayazaGateway;
use App\Integrations\Payments\VerificationStatus;
use App\Integrations\Vending\StronVendor;
use App\Integrations\Vending\VendOutcome;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class ProviderAdaptersTest extends TestCase
{
    use BuildsEstates;

    public function test_payaza_live_webhook_shape_is_normalised(): void
    {
        // Shape taken from the legacy production webhook log (values anonymised).
        $payload = ['transaction_reference' => 'P-C-20261023-XXXX', 'transaction_status' => 'Funds Received', 'transaction_fee' => 100.00, 'amount_received' => 20350.00,
            'merchant_reference' => 'PNE-ABC', 'status' => 'Completed', 'channel' => 'VirtualAccount', 'currency_code' => 'NGN', 'request_amount' => 20350.0,
            'received_from' => ['account_name' => 'A N OTHER', 'account_number' => '0123456789'], 'customer' => ['email_address' => 'x@example.com']];

        $gateway = new PayazaGateway;
        $result = $gateway->fromPayload('PNE-ABC', $payload);

        $this->assertSame(VerificationStatus::Succeeded, $result->status);
        $this->assertSame(2_035_000, $result->amount);
        $this->assertSame('NGN', $result->currency);
        $this->assertSame('PNE-ABC', $gateway->webhookReference($payload));
        $this->assertArrayNotHasKey('received_from', $result->raw);

        $this->assertSame(VerificationStatus::Failed, $gateway->fromPayload('PNE-ABC', ['transaction_status' => 'Transaction Failed'])->status);
        $this->assertSame(VerificationStatus::Pending, $gateway->fromPayload('PNE-ABC', ['transaction_status' => 'Awaiting Payment'])->status);
    }

    public function test_stron_vend_request_matches_legacy_contract_and_redacts_credentials(): void
    {
        $this->enableIntegrations();
        Http::fake(['stron.test/api/VendingMeter' => Http::response([['Token' => '12345678901234567890', 'Total_unit' => '44.4', 'Unit' => 'kWh', 'Username' => 'tester', 'Company_name' => 'TestCo']])]);

        $result = (new StronVendor)->vend('0472100000', 1_000_000, 'PNE-X');

        $this->assertSame(VendOutcome::Success, $result->outcome);
        $this->assertSame('44.40', $result->units);
        $this->assertArrayNotHasKey('Token', $result->raw);
        $this->assertArrayNotHasKey('Username', $result->raw);
        Http::assertSent(fn (Request $r) => $r['CompanyName'] === 'TestCo' && $r['UserName'] === 'tester' && $r['PassWord'] === 'secret-pass'
            && $r['MeterID'] === '0472100000' && $r['is_vend_by_unit'] === 'false' && $r['Amount'] == 10000.0);
    }

    public function test_stron_meter_lookup_never_fakes_success(): void
    {
        $this->enableIntegrations();
        Http::fake(['stron.test/api/QueryMeterInfo' => Http::sequence()
            ->push([['Meter_id' => '0472100000', 'Customer_name' => 'Ada', 'Meter_type' => 'Single']])
            ->push([])
            ->push('<html>error</html>', 500)]);

        $vendor = new StronVendor;
        $this->assertSame('Ada', $vendor->lookupMeter('0472100000')->customerName);
        $this->assertNull($vendor->lookupMeter('0472100000'));
        $this->expectException(IntegrationException::class);
        $vendor->lookupMeter('0472100000');
    }
}
