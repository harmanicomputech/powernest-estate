<?php

namespace Tests\Feature\Operations;

use App\Enums\EstateRole;
use App\Enums\InvoiceStatus;
use App\Enums\PowerPurchaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\PaymentSubmission;
use App\Models\PowerPurchase;
use App\Models\PowerToken;
use App\Models\Property;
use App\Models\User;
use App\Notifications\PaymentSubmissionReceived;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class BankTransferBillsTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    private Estate $estateModel;

    private User $resident;

    private Property $property;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');

        $this->estateModel = $this->estate(['billing' => ['bank_name' => 'GTBank', 'bank_account_name' => 'Greenfield Court Estate', 'bank_account_number' => '0123456789']]);
        [$this->resident, $this->property] = $this->resident($this->estateModel);
        $this->finance = $this->staff($this->estateModel, EstateRole::FinanceOfficer);
    }

    public function test_service_charge_is_bank_transfer_only_and_needs_a_receipt(): void
    {
        $invoice = app(BillingService::class)->issueInvoice($this->estateModel, $this->property, [['description' => 'Service charge', 'quantity' => 1, 'unit_amount' => 4_500_000]], 'Service charge', now()->addWeek(), $this->finance);

        $this->inEstate($this->resident, $this->estateModel)->get(route('bills.show', $invoice))
            ->assertOk()->assertSee('Pay by bank transfer')->assertSee('0123456789')->assertSee('Greenfield Court Estate')
            ->assertDontSee('Or pay online')->assertDontSee('I have paid');
        $this->inEstate($this->resident, $this->estateModel)->post(route('bills.pay', $invoice), ['gateway' => 'paystack'])->assertNotFound();

        $form = ['amount' => '45,000', 'method' => 'bank_transfer', 'paid_on' => now()->toDateString()];
        $this->inEstate($this->resident, $this->estateModel)->post(route('bills.receipts.store', $invoice), $form)->assertSessionHasErrors('receipt');
        $this->inEstate($this->resident, $this->estateModel)->post(route('bills.receipts.store', $invoice), $form + ['receipt' => UploadedFile::fake()->image('transfer.jpg')])
            ->assertSessionHasNoErrors();
        $this->assertNotNull(PaymentSubmission::firstOrFail()->receipt);
    }

    public function test_levy_i_have_paid_is_confirmed_by_admin_with_house_information(): void
    {
        $charge = ChargeType::create(['estate_id' => $this->estateModel->id, 'name' => 'Estate management & security levy', 'code' => 'LEVY', 'amount' => 100_000_000, 'frequency' => 'annual']);
        $year = now('Africa/Lagos')->startOfYear();
        app(BillingService::class)->runCharge($charge, $year, $year->copy()->endOfYear(), now()->addMonth(), $this->finance);
        $levy = Invoice::firstOrFail();

        $this->inEstate($this->resident, $this->estateModel)->get(route('bills.show', $levy))->assertOk()->assertSee('I have paid')->assertSee('0123456789');

        $this->inEstate($this->resident, $this->estateModel)->post(route('bills.receipts.store', $levy), [
            'amount' => '1,000,000', 'method' => 'bank_transfer', 'paid_on' => now()->toDateString(), 'payer_reference' => 'ADE OBI',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $submission = PaymentSubmission::firstOrFail();
        $this->assertNull($submission->receipt);
        Notification::assertSentTo($this->finance, PaymentSubmissionReceived::class, fn ($n) => str_contains($n->toArray($this->finance)['body'], 'says they paid'));

        $this->inEstate($this->resident, $this->estateModel)->get(route('bills.show', $levy))->assertSee('Payment being confirmed')->assertDontSee('Send for confirmation');

        $this->inEstate($this->finance, $this->estateModel)->get(route('manage.billing.submissions.index'))->assertOk()->assertSee('I have paid');
        $this->inEstate($this->finance, $this->estateModel)->get(route('manage.billing.submissions.show', $submission))->assertOk()
            ->assertSee('House information')->assertSee($this->property->identifier)->assertSee($this->resident->name)
            ->assertSee('Yearly levy history')->assertSee('no receipt attached')->assertSee('ADE OBI');

        $this->inEstate($this->finance, $this->estateModel)->post(route('manage.billing.submissions.approve', $submission), ['amount' => '1000000.00', 'note' => 'Seen on GTB statement'])->assertRedirect();
        $this->assertSame(SubmissionStatus::Approved, $submission->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $levy->fresh()->status);
    }

    public function test_token_can_be_sent_on_whatsapp(): void
    {
        $meter = $this->meterFor($this->property);
        $purchase = PowerPurchase::create([
            'reference' => 'PNE-WHATSAPP1', 'estate_id' => $this->estateModel->id, 'user_id' => $this->resident->id, 'meter_id' => $meter->id,
            'property_id' => $this->property->id, 'meter_number' => $meter->meter_number, 'gateway' => 'paystack', 'vend_amount' => 500_000,
            'service_charge' => 35_000, 'total_amount' => 535_000, 'amount_paid' => 535_000, 'status' => PowerPurchaseStatus::TokenIssued->value,
            'paid_at' => now(), 'token_issued_at' => now(),
        ]);
        PowerToken::create(['power_purchase_id' => $purchase->id, 'token' => '11112222333344445555', 'units' => '22.00', 'meter_number' => $meter->meter_number, 'issued_at' => now()]);

        $this->inEstate($this->resident, $this->estateModel)->get(route('power.purchases.show', $purchase))->assertOk()
            ->assertSee('Send on WhatsApp')->assertSee('https://wa.me/?text=', false)->assertSee(rawurlencode('1111 2222 3333 4444 5555'), false);
    }
}
