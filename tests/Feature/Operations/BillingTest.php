<?php

namespace Tests\Feature\Operations;

use App\Enums\EstateRole;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\ChargeType;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Services\Billing\BillingPaymentService;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_recurring_charge_bills_each_occupied_property_once_per_period(): void
    {
        $estate = $this->estate();
        $finance = $this->staff($estate, EstateRole::FinanceOfficer);
        $this->resident($estate);
        $this->resident($estate);
        Property::factory()->create(['estate_id' => $estate->id]); // vacant
        $charge = ChargeType::create(['estate_id' => $estate->id, 'name' => 'Service charge', 'code' => 'SVC', 'amount' => 4_500_000, 'frequency' => 'monthly']);

        $period = Carbon::parse('first day of this month');
        $first = app(BillingService::class)->runCharge($charge, $period, $period->copy()->endOfMonth(), now()->addDays(14), $finance);
        $second = app(BillingService::class)->runCharge($charge, $period, $period->copy()->endOfMonth(), now()->addDays(14), $finance);

        $this->assertSame(['issued' => 2, 'skipped' => 0], $first);
        $this->assertSame(['issued' => 0, 'skipped' => 2], $second);
        $this->assertSame(9_000_000, (int) Invoice::sum('total'));
        $this->assertSame(2, LedgerEntry::where('entry_type', 'invoice_issued')->count());
    }

    public function test_manual_payment_allocates_and_updates_ledger_balance(): void
    {
        $estate = $this->estate();
        $finance = $this->staff($estate, EstateRole::FinanceOfficer);
        [, $property] = $this->resident($estate);
        $billing = app(BillingService::class);

        $invoice = $billing->issueInvoice($estate, $property, [['description' => 'Levy', 'quantity' => 2, 'unit_amount' => 1_000_050]], 'Levy', now()->addWeek(), $finance);
        $this->assertSame(2_000_100, $invoice->total);
        $this->assertSame(2_000_100, $billing->propertyBalance($property));

        $billing->recordManualPayment($invoice, 1_000_000, 'bank_transfer', 'TRF-1', now(), $finance);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertSame(1_000_100, $billing->propertyBalance($property));

        // Overpayment and duplicate references are refused.
        try {
            $billing->recordManualPayment($invoice->fresh(), 5_000_000, 'bank_transfer', 'TRF-2', now(), $finance);
            $this->fail('Overpayment accepted');
        } catch (ValidationException) {
        }
        try {
            $billing->recordManualPayment($invoice->fresh(), 100, 'bank_transfer', 'TRF-1', now(), $finance);
            $this->fail('Duplicate reference accepted');
        } catch (ValidationException) {
        }

        $billing->recordManualPayment($invoice->fresh(), 1_000_100, 'pos', 'POS-77', now(), $finance);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(0, $billing->propertyBalance($property));
    }

    public function test_waiver_needs_a_second_approver(): void
    {
        $estate = $this->estate();
        $finance = $this->staff($estate, EstateRole::FinanceOfficer);
        $manager = $this->staff($estate, EstateRole::EstateManager);
        [, $property] = $this->resident($estate);
        $billing = app(BillingService::class);
        $invoice = $billing->issueInvoice($estate, $property, [['description' => 'Levy', 'quantity' => 1, 'unit_amount' => 1_000_000]], 'Levy', now()->addWeek(), $finance);

        $adjustment = $billing->requestAdjustment($invoice, 'waiver', 250_000, 'Hardship', $finance);

        try {
            $billing->reviewAdjustment($adjustment, $finance, true);
            $this->fail('Self-approval allowed');
        } catch (ValidationException) {
        }

        $billing->reviewAdjustment($adjustment->fresh(), $manager, true);
        $this->assertSame(750_000, $invoice->fresh()->total);
        $this->assertSame(750_000, $billing->propertyBalance($property));
    }

    public function test_resident_sees_only_own_invoices(): void
    {
        $estate = $this->estate();
        $finance = $this->staff($estate, EstateRole::FinanceOfficer);
        [$alice, $aliceHome] = $this->resident($estate);
        [$bob, $bobHome] = $this->resident($estate);
        $billing = app(BillingService::class);
        $bobInvoice = $billing->issueInvoice($estate, $bobHome, [['description' => 'Levy', 'quantity' => 1, 'unit_amount' => 100_000]], 'Bob levy', now()->addWeek(), $finance);

        $this->inEstate($alice, $estate)->get(route('bills.show', $bobInvoice))->assertForbidden();
        $this->inEstate($alice, $estate)->get(route('bills.index'))->assertOk()->assertDontSee('Bob levy');
        $this->inEstate($bob, $estate)->get(route('bills.show', $bobInvoice))->assertOk()->assertSee('Bob levy');
        $this->inEstate($bob, $estate)->get(route('manage.billing.index'))->assertForbidden();
    }

    public function test_online_invoice_payment_is_verified_server_side_and_idempotent(): void
    {
        $this->enableIntegrations();
        $estate = $this->estate();
        $finance = $this->staff($estate, EstateRole::FinanceOfficer);
        [$resident, $property] = $this->resident($estate);
        $invoice = app(BillingService::class)->issueInvoice($estate, $property, [['description' => 'Service charge', 'quantity' => 1, 'unit_amount' => 4_500_000]], 'Service charge', now()->addWeek(), $finance);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.test/abc', 'access_code' => 'abc']]),
            'api.paystack.co/transaction/verify/*' => function (Request $request) {
                $ref = basename(parse_url($request->url(), PHP_URL_PATH));

                return Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => $ref, 'amount' => 4_500_000, 'currency' => 'NGN', 'id' => 5]]);
            },
        ]);

        $this->inEstate($resident, $estate)->post(route('bills.pay', $invoice), ['gateway' => 'paystack'])->assertRedirect('https://checkout.paystack.test/abc');
        $payment = Payment::firstOrFail();
        $this->assertStringStartsWith('PNB-', $payment->reference);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/initialize') && $r['amount'] === 4_500_000 && $r['reference'] === $payment->reference);

        $service = app(BillingPaymentService::class);
        $service->verifyWithGateway($payment, 'callback');
        $service->verifyWithGateway($payment->fresh(), 'webhook');

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(1, $payment->fresh()->allocations()->count());
        $this->assertSame(0, app(BillingService::class)->propertyBalance($property));
        // Estate payments never create electricity purchases.
        $this->assertDatabaseCount('power_purchases', 0);
    }

    public function test_ledger_entries_are_immutable(): void
    {
        $estate = $this->estate();
        $finance = $this->staff($estate, EstateRole::FinanceOfficer);
        [, $property] = $this->resident($estate);
        app(BillingService::class)->issueInvoice($estate, $property, [['description' => 'Levy', 'quantity' => 1, 'unit_amount' => 100]], 'Levy', now()->addWeek(), $finance);

        $this->expectException(\LogicException::class);
        LedgerEntry::first()->update(['debit' => 0]);
    }
}
