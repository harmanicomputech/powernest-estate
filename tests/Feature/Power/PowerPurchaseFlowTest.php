<?php

namespace Tests\Feature\Power;

use App\Enums\EstateRole;
use App\Enums\PowerPurchaseStatus as Status;
use App\Models\Meter;
use App\Models\PowerPurchase;
use App\Models\PowerToken;
use App\Models\User;
use App\Notifications\PowerPurchaseNeedsAttention;
use App\Notifications\PowerTokenIssued;
use App\Services\Power\PowerPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class PowerPurchaseFlowTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    private User $resident;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableIntegrations();
        Notification::fake();

        $this->estateModel = $this->estate();
        [$this->resident, $property] = $this->resident($this->estateModel);
        $this->meter = $this->meterFor($property);
    }

    private $estateModel;

    private function startPurchase(int $naira = 5000, string $gateway = 'paystack'): PowerPurchase
    {
        $this->inEstate($this->resident, $this->estateModel)
            ->post(route('power.purchases.store'), ['meter_id' => $this->meter->id, 'amount' => (string) $naira, 'gateway' => $gateway])
            ->assertRedirect();

        return PowerPurchase::latest('id')->firstOrFail();
    }

    private function fakePaystack(string $status = 'success', ?int $amount = null, string $currency = 'NGN', ?string $reference = null): void
    {
        Http::fake([
            'api.paystack.co/transaction/verify/*' => function (Request $request) use ($status, $amount, $currency, $reference) {
                $ref = $reference ?? urldecode(basename(parse_url($request->url(), PHP_URL_PATH)));
                $purchase = PowerPurchase::where('reference', $ref)->first();

                return Http::response(['status' => true, 'message' => 'Verification successful', 'data' => [
                    'id' => 99887766, 'status' => $status, 'reference' => $ref,
                    'amount' => $amount ?? $purchase?->total_amount, 'currency' => $currency,
                    'channel' => 'card', 'paid_at' => now()->toIso8601String(), 'gateway_response' => 'Approved',
                ]]);
            },
        ]);
    }

    private function fakeStronSuccess(): void
    {
        Http::fake(['stron.test/api/VendingMeter' => Http::response([[
            'Company_name' => 'TestCo', 'Username' => 'tester', 'Customer_name' => 'Ada', 'Meter_id' => $this->meter->meter_number,
            'Meter_type' => 'Single phase', 'Total_unit' => '22.22', 'Unit' => 'kWh', 'Price' => '225', 'Token' => '12345678901234567890', 'Gen_time' => '2026-10-23 10:00:00',
        ]])]);
    }

    public function test_server_computes_amount_and_service_charge(): void
    {
        $purchase = $this->startPurchase(5000);

        $this->assertSame(Status::PendingPayment, $purchase->status);
        $this->assertSame(500_000, $purchase->vend_amount);
        $this->assertSame(25_000, $purchase->service_charge);
        $this->assertSame(525_000, $purchase->total_amount);
        $this->assertStringStartsWith('PNE-', $purchase->reference);
    }

    public function test_amount_outside_estate_limits_is_rejected(): void
    {
        $this->inEstate($this->resident, $this->estateModel)
            ->post(route('power.purchases.store'), ['meter_id' => $this->meter->id, 'amount' => '50', 'gateway' => 'paystack'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, PowerPurchase::count());
    }

    public function test_cannot_buy_for_a_meter_on_someone_elses_property(): void
    {
        [, $otherProperty] = $this->resident($this->estateModel);
        $otherMeter = $this->meterFor($otherProperty);

        $this->inEstate($this->resident, $this->estateModel)
            ->post(route('power.purchases.store'), ['meter_id' => $otherMeter->id, 'amount' => '5000', 'gateway' => 'paystack'])
            ->assertSessionHasErrors('meter_id');
    }

    public function test_successful_payment_then_vend_issues_exactly_one_token(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack();
        $this->fakeStronSuccess();

        $this->inEstate($this->resident, $this->estateModel)->get(route('power.purchases.return', $purchase))->assertRedirect(route('power.purchases.show', $purchase));

        $purchase->refresh();
        $this->assertSame(Status::TokenIssued, $purchase->status);
        $this->assertSame('12345678901234567890', $purchase->token->token);
        $this->assertSame('22.22', $purchase->token->units);
        $this->assertSame('callback', $purchase->payment_confirmed_via);
        Notification::assertSentTo($this->resident, PowerTokenIssued::class);

        // Vended only the electricity value, never the service charge.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/VendingMeter') && $r['Amount'] == 5000.0 && $r['MeterID'] === $this->meter->meter_number);
        // The token is encrypted at rest.
        $this->assertNotSame('12345678901234567890', \DB::table('power_tokens')->value('token'));
    }

    public function test_token_page_shows_token_only_after_issue(): void
    {
        $purchase = $this->startPurchase();

        $this->inEstate($this->resident, $this->estateModel)->get(route('power.purchases.show', $purchase))
            ->assertOk()->assertSee('Waiting for payment')->assertDontSee('Your electricity token');
    }

    public function test_failed_payment_does_not_vend(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack('failed');

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');

        $this->assertSame(Status::PaymentFailed, $purchase->fresh()->status);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'VendingMeter'));
    }

    public function test_pending_payment_stays_pending(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack('ongoing');

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');

        $this->assertSame(Status::PendingPayment, $purchase->fresh()->status);
        $this->assertNotNull($purchase->fresh()->last_verified_at);
    }

    public function test_underpayment_requires_reconciliation_and_never_vends(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack('success', amount: 100_000);

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');

        $purchase->refresh();
        $this->assertSame(Status::ReconciliationRequired, $purchase->status);
        $this->assertSame('amount_mismatch', $purchase->failure_code);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'VendingMeter'));
    }

    public function test_wrong_currency_requires_reconciliation(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack('success', currency: 'USD');

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');

        $this->assertSame('currency_mismatch', $purchase->fresh()->failure_code);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'VendingMeter'));
    }

    public function test_verification_for_a_different_reference_is_ignored(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack('success', amount: 525_000, reference: 'PNE-SOMETHINGELSE');

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');

        $this->assertSame(Status::PendingPayment, $purchase->fresh()->status);
    }

    public function test_invalid_paystack_webhook_signature_is_rejected(): void
    {
        $purchase = $this->startPurchase();
        $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => $purchase->reference]]);

        $this->call('POST', route('webhooks.paystack'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => 'forged'], $payload)
            ->assertStatus(401);

        $this->assertSame(Status::PendingPayment, $purchase->fresh()->status);
        $this->assertDatabaseCount('gateway_webhook_events', 0);
    }

    public function test_duplicate_signed_webhooks_vend_only_once(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack();
        $this->fakeStronSuccess();

        $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => $purchase->reference, 'amount' => $purchase->total_amount]]);
        $signature = hash_hmac('sha512', $payload, 'sk_test_fake_secret');
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature];

        $this->call('POST', route('webhooks.paystack'), [], [], [], $server, $payload)->assertOk();
        $this->call('POST', route('webhooks.paystack'), [], [], [], $server, $payload)->assertOk()->assertJson(['message' => 'Duplicate ignored']);
        // A replay with different bytes (e.g. retried with new whitespace) is re-verified but cannot vend again.
        $variant = $payload.' ';
        $this->call('POST', route('webhooks.paystack'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $variant, 'sk_test_fake_secret')], $variant)->assertOk();

        $this->assertSame(Status::TokenIssued, $purchase->fresh()->status);
        $this->assertSame('webhook', $purchase->fresh()->payment_confirmed_via);
        $this->assertSame(1, PowerToken::count());
        Http::assertSentCount(2); // one verification + one vend; the replay stopped at the idempotency check
    }

    public function test_browser_callback_after_webhook_does_not_vend_again(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack();
        $this->fakeStronSuccess();

        $service = app(PowerPurchaseService::class);
        $service->verifyWithGateway($purchase, 'webhook');
        $service->verifyWithGateway($purchase->fresh(), 'callback');
        $service->vend($purchase->fresh());

        $this->assertSame(1, PowerToken::count());
        Http::assertSentCount(2);
    }

    public function test_vend_timeout_requires_reconciliation_and_is_not_retried(): void
    {
        $manager = $this->staff($this->estateModel, EstateRole::EstateManager);
        $purchase = $this->startPurchase();
        $this->fakePaystack();
        Http::fake(['stron.test/api/VendingMeter' => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 45001 milliseconds')]);

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');

        $purchase->refresh();
        $this->assertSame(Status::ReconciliationRequired, $purchase->status);
        $this->assertSame('vend_unknown', $purchase->failure_code);
        $this->assertSame(1, $purchase->vend_attempts);
        $this->assertSame(0, PowerToken::count());
        Notification::assertSentTo($manager, PowerPurchaseNeedsAttention::class);
        Notification::assertNotSentTo($this->resident, PowerTokenIssued::class);
    }

    public function test_undelivered_vend_request_is_retried_automatically(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack();
        $calls = 0;
        Http::fake(['stron.test/api/VendingMeter' => function () use (&$calls) {
            if (++$calls === 1) {
                throw new ConnectionException('cURL error 7: Failed to connect to stron.test port 443');
            }

            return Http::response([['Token' => '99998888777766665555', 'Total_unit' => '10.00', 'Unit' => 'kWh', 'Meter_id' => $this->meter->meter_number]]);
        }]);

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');

        $purchase->refresh();
        $this->assertSame(Status::TokenIssued, $purchase->status);
        $this->assertSame(2, $purchase->vend_attempts);
        $this->assertSame(['not_sent', 'success'], $purchase->vendAttempts->pluck('outcome')->all());
    }

    public function test_paid_but_vend_declined_can_be_recovered_by_finance(): void
    {
        $this->staff($this->estateModel, EstateRole::EstateManager);
        $finance = $this->staff($this->estateModel, EstateRole::FinanceOfficer);
        $purchase = $this->startPurchase();
        $this->fakePaystack();
        Http::fake(['stron.test/api/VendingMeter' => Http::sequence()
            ->push([['Message' => 'Meter is locked']], 200)
            ->push([['Token' => '11112222333344445555', 'Total_unit' => '22.22', 'Unit' => 'kWh']], 200)]);

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');
        $this->assertSame(Status::VendFailed, $purchase->fresh()->status);

        // The resident cannot trigger a retry.
        $this->inEstate($this->resident, $this->estateModel)->post(route('manage.power.retry', $purchase), ['note' => 'x', 'confirm_no_token' => '1'])->assertForbidden();

        // Finance must explicitly confirm no token exists.
        $this->inEstate($finance, $this->estateModel)->post(route('manage.power.retry', $purchase), ['note' => 'Checked portal'])->assertSessionHasErrors('confirm_no_token');
        $this->inEstate($finance, $this->estateModel)->post(route('manage.power.retry', $purchase), ['note' => 'Checked Stron portal, nothing issued', 'confirm_no_token' => '1'])->assertRedirect();

        $purchase->refresh();
        $this->assertSame(Status::TokenIssued, $purchase->status);
        $this->assertSame($finance->id, $purchase->vendAttempts->last()->initiated_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'power.vend_retry_requested', 'user_id' => $finance->id]);
    }

    public function test_manual_token_recording_for_unresolved_purchase(): void
    {
        $finance = $this->staff($this->estateModel, EstateRole::FinanceOfficer);
        $purchase = $this->startPurchase();
        $this->fakePaystack();
        Http::fake(['stron.test/api/VendingMeter' => Http::response('<html>Bad gateway</html>', 502)]);

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');
        $this->assertSame(Status::ReconciliationRequired, $purchase->fresh()->status);

        $this->inEstate($finance, $this->estateModel)
            ->post(route('manage.power.token', $purchase), ['token' => '5555 4444 3333 2222 1111', 'units' => '22.5', 'note' => 'Found in Stron portal'])
            ->assertRedirect();

        $purchase->refresh();
        $this->assertSame(Status::TokenIssued, $purchase->status);
        $this->assertSame('55554444333322221111', $purchase->token->token);
        $this->assertSame('manual', $purchase->token->source);
        Notification::assertSentTo($this->resident, PowerTokenIssued::class);
    }

    public function test_provider_downtime_then_recovery_via_sweep(): void
    {
        $purchase = $this->startPurchase();
        $providerUp = false;
        Http::fake([
            'api.paystack.co/*' => function () use (&$providerUp, $purchase) {
                return $providerUp
                    ? Http::response(['status' => true, 'data' => ['id' => 1, 'status' => 'success', 'reference' => $purchase->reference, 'amount' => $purchase->total_amount, 'currency' => 'NGN']])
                    : Http::response(['status' => false, 'message' => 'Service unavailable'], 503);
            },
        ]);
        $this->fakeStronSuccess();

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');
        $this->assertSame(Status::PendingPayment, $purchase->fresh()->status);

        // Provider recovers; the scheduled sweep picks the purchase up.
        $providerUp = true;
        $this->travel(6)->minutes();
        $stats = app(PowerPurchaseService::class)->sweep();

        $this->assertSame(1, $stats['verified']);
        $this->assertSame(Status::TokenIssued, $purchase->fresh()->status);
        $this->assertSame('reconciliation', $purchase->fresh()->payment_confirmed_via);
    }

    public function test_abandoned_purchases_expire_but_late_payment_is_still_honoured(): void
    {
        $purchase = $this->startPurchase();
        $this->travel(2)->days();

        app(PowerPurchaseService::class)->sweep();
        $this->assertSame(Status::Expired, $purchase->fresh()->status);

        $this->fakePaystack();
        $this->fakeStronSuccess();
        app(PowerPurchaseService::class)->verifyWithGateway($purchase->fresh(), 'webhook');

        $this->assertSame(Status::TokenIssued, $purchase->fresh()->status);
    }

    public function test_stuck_vending_is_flagged_not_retried(): void
    {
        $purchase = $this->startPurchase();
        $purchase->forceFill(['status' => Status::Vending, 'amount_paid' => $purchase->total_amount, 'last_vend_attempt_at' => now()->subMinutes(20), 'vend_attempts' => 1])->save();

        app(PowerPurchaseService::class)->sweep();

        $this->assertSame(Status::ReconciliationRequired, $purchase->fresh()->status);
        $this->assertSame('vend_interrupted', $purchase->fresh()->failure_code);
        Http::assertNothingSent();
    }

    public function test_token_can_be_redelivered_by_owner_only(): void
    {
        $purchase = $this->startPurchase();
        $this->fakePaystack();
        $this->fakeStronSuccess();
        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');
        Notification::fake();

        $this->inEstate($this->resident, $this->estateModel)->post(route('power.purchases.resend', $purchase))->assertRedirect();
        Notification::assertSentTo($this->resident, PowerTokenIssued::class);

        [$neighbour] = $this->resident($this->estateModel);
        $this->inEstate($neighbour, $this->estateModel)->post(route('power.purchases.resend', $purchase))->assertForbidden();
        $this->inEstate($neighbour, $this->estateModel)->get(route('power.purchases.show', $purchase))->assertForbidden();
    }

    public function test_purchases_from_another_estate_are_not_found(): void
    {
        $purchase = $this->startPurchase();
        $otherEstate = $this->estate();
        $otherManager = $this->staff($otherEstate, EstateRole::EstateManager);

        $this->inEstate($otherManager, $otherEstate)->get(route('manage.power.show', $purchase))->assertNotFound();
        $this->inEstate($otherManager, $otherEstate)->get(route('power.purchases.show', $purchase))->assertNotFound();
    }

    public function test_payaza_unsigned_webhook_only_triggers_server_verification(): void
    {
        $purchase = $this->startPurchase(5000, 'payaza');
        Http::fake([
            'payaza.test/*' => Http::response(['data' => ['merchant_reference' => $purchase->reference, 'transaction_status' => 'Funds Received', 'amount_received' => 5350.00, 'currency_code' => 'NGN', 'transaction_reference' => 'P-C-1', 'channel' => 'VirtualAccount']]),
        ]);
        $this->fakeStronSuccess();

        // Wrong URL secret: rejected before anything is stored.
        $this->postJson('/webhooks/payaza/wrong', ['merchant_reference' => $purchase->reference, 'transaction_status' => 'Funds Received'])->assertNotFound();
        $this->assertSame(Status::PendingPayment, $purchase->fresh()->status);

        // Correct URL: the webhook body claims payment, but the status comes from the server-side query.
        $this->postJson('/webhooks/payaza/hook-secret', ['merchant_reference' => $purchase->reference, 'transaction_status' => 'Funds Received', 'amount_received' => 999999])->assertOk();

        $purchase->refresh();
        $this->assertSame(Status::TokenIssued, $purchase->status);
        $this->assertSame(535_000, $purchase->amount_paid);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'payaza.test') && $r->hasHeader('Authorization', 'Payaza '.base64_encode('PZ78-PKTEST-FAKE')));
    }

    public function test_payaza_without_status_endpoint_never_confirms_from_webhook_alone(): void
    {
        config(['services.payaza.verify_url' => null]);
        $purchase = $this->startPurchase(5000, 'payaza');

        $this->postJson('/webhooks/payaza/hook-secret', ['merchant_reference' => $purchase->reference, 'transaction_status' => 'Funds Received', 'amount_received' => 5350])->assertOk();

        $this->assertSame(Status::PendingPayment, $purchase->fresh()->status);
        $this->assertDatabaseHas('gateway_webhook_events', ['reference' => $purchase->reference, 'gateway' => 'payaza']);
        Http::assertNothingSent();
    }

    public function test_integrations_kill_switch_blocks_outbound_calls(): void
    {
        $purchase = $this->startPurchase();
        config(['powernest.integrations_live' => false]);

        app(PowerPurchaseService::class)->verifyWithGateway($purchase, 'callback');

        $this->assertSame(Status::PendingPayment, $purchase->fresh()->status);
        Http::assertNothingSent();
    }
}
