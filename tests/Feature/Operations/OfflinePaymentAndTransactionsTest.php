<?php

namespace Tests\Feature\Operations;

use App\Enums\EstateRole;
use App\Enums\InvoiceStatus;
use App\Enums\OccupancyRelationship;
use App\Enums\SubmissionStatus;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\PaymentSubmission;
use App\Models\Property;
use App\Models\User;
use App\Notifications\PaymentSubmissionReceived;
use App\Notifications\PaymentSubmissionReviewed;
use App\Services\Billing\BillingService;
use App\Services\Estate\OccupancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class OfflinePaymentAndTransactionsTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    private Estate $estateModel;

    private User $resident;

    private Property $property;

    private User $finance;

    private Invoice $levy;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');

        $this->estateModel = $this->estate();
        [$this->resident, $this->property] = $this->resident($this->estateModel);
        $this->finance = $this->staff($this->estateModel, EstateRole::FinanceOfficer);

        $charge = ChargeType::create(['estate_id' => $this->estateModel->id, 'name' => 'Estate management & security levy', 'code' => 'LEVY', 'amount' => 25_000_000, 'frequency' => 'annual']);
        $year = now('Africa/Lagos')->startOfYear();
        app(BillingService::class)->runCharge($charge, $year, $year->copy()->endOfYear(), now()->addMonth(), $this->finance);
        $this->levy = Invoice::firstOrFail();
    }

    private function upload(User $user, array $overrides = []): TestResponse
    {
        return $this->inEstate($user, $this->estateModel)->post(route('bills.receipts.store', $this->levy), $overrides + [
            'amount' => '250,000', 'method' => 'bank_transfer', 'payer_reference' => 'GTB-778899', 'paid_on' => now()->toDateString(),
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
    }

    public function test_dashboard_shows_levy_due_then_under_review_then_paid_for_the_year(): void
    {
        $year = (string) now('Africa/Lagos')->year;

        $this->inEstate($this->resident, $this->estateModel)->get(route('dashboard'))->assertSee("Estate management &amp; security levy {$year} is due", false);

        $this->upload($this->resident)->assertSessionHasNoErrors()->assertRedirect();
        $submission = PaymentSubmission::firstOrFail();
        $this->assertSame(SubmissionStatus::Pending, $submission->status);
        $this->assertNotNull($submission->receipt);
        Notification::assertSentTo($this->finance, PaymentSubmissionReceived::class);
        $this->assertSame(InvoiceStatus::Issued, $this->levy->fresh()->status, 'Nothing is credited before review');

        $this->inEstate($this->resident, $this->estateModel)->get(route('dashboard'))->assertSee('receipt under review');

        $this->inEstate($this->finance, $this->estateModel)->post(route('manage.billing.submissions.approve', $submission), ['amount' => '250000.00', 'note' => 'Matched statement'])->assertRedirect();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::Approved, $submission->status);
        $this->assertSame(InvoiceStatus::Paid, $this->levy->fresh()->status);
        $this->assertSame('bank_transfer', $submission->payment->method);
        $this->assertSame(0, app(BillingService::class)->propertyBalance($this->property));
        Notification::assertSentTo($this->resident, PaymentSubmissionReviewed::class);

        $this->inEstate($this->resident, $this->estateModel)->get(route('dashboard'))->assertSee("paid for {$year}");

        // Cannot be approved twice.
        $this->inEstate($this->finance, $this->estateModel)->post(route('manage.billing.submissions.approve', $submission), ['amount' => '250000'])->assertSessionHasErrors('submission');
    }

    public function test_rejection_notifies_resident_who_can_resubmit(): void
    {
        $this->upload($this->resident);
        $submission = PaymentSubmission::firstOrFail();

        // Only one receipt pending at a time.
        $this->upload($this->resident)->assertSessionHasErrors('receipt');

        $this->inEstate($this->finance, $this->estateModel)->post(route('manage.billing.submissions.reject', $submission), ['reason' => 'Not on statement'])->assertRedirect();
        $this->assertSame(SubmissionStatus::Rejected, $submission->fresh()->status);
        $this->assertSame(InvoiceStatus::Issued, $this->levy->fresh()->status);
        Notification::assertSentTo($this->resident, PaymentSubmissionReviewed::class, fn ($n) => str_contains($n->toArray($this->resident)['body'], 'Not on statement'));

        $this->inEstate($this->resident, $this->estateModel)->get(route('bills.show', $this->levy))->assertSee('Not on statement')->assertSee('Paid offline?');
        $this->upload($this->resident)->assertSessionHasNoErrors();
        $this->assertSame(2, PaymentSubmission::count());
    }

    public function test_partial_approval_leaves_balance_outstanding(): void
    {
        $this->upload($this->resident);

        $this->inEstate($this->finance, $this->estateModel)->post(route('manage.billing.submissions.approve', PaymentSubmission::first()), ['amount' => '100000'])->assertRedirect();

        $this->assertSame(InvoiceStatus::PartiallyPaid, $this->levy->fresh()->status);
        $this->assertSame(15_000_000, $this->levy->fresh()->balance());
    }

    public function test_upload_and_review_are_authorized(): void
    {
        [$neighbour] = $this->resident($this->estateModel);
        $guard = $this->staff($this->estateModel, EstateRole::SecurityOfficer);

        $this->upload($neighbour)->assertForbidden();
        $this->upload($this->resident, ['amount' => '999999'])->assertSessionHasErrors('amount');
        $this->upload($this->resident, ['receipt' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('receipt');

        $this->upload($this->resident);
        $submission = PaymentSubmission::firstOrFail();

        $this->inEstate($guard, $this->estateModel)->get(route('manage.billing.submissions.index'))->assertForbidden();
        $this->inEstate($this->resident, $this->estateModel)->post(route('manage.billing.submissions.approve', $submission), ['amount' => '250000'])->assertForbidden();

        $this->inEstate($this->resident, $this->estateModel)->get(route('documents.show', $submission->receipt))->assertOk();
        $this->inEstate($neighbour, $this->estateModel)->get(route('documents.show', $submission->receipt))->assertForbidden();
        $this->inEstate($this->finance, $this->estateModel)->get(route('manage.billing.submissions.show', $submission))->assertOk()->assertSee('GTB-778899');
    }

    public function test_staff_cannot_approve_their_own_receipt(): void
    {
        $treasurer = $this->staff($this->estateModel, EstateRole::FinanceOfficer);
        app(OccupancyService::class)->addOccupant($this->property, $treasurer, OccupancyRelationship::Owner);

        $this->upload($treasurer->fresh())->assertSessionHasNoErrors();
        $this->inEstate($treasurer->fresh(), $this->estateModel)->post(route('manage.billing.submissions.approve', PaymentSubmission::first()), ['amount' => '250000'])->assertSessionHasErrors('submission');
    }

    public function test_transactions_page_and_pdf_downloads(): void
    {
        $this->upload($this->resident);
        $this->inEstate($this->finance, $this->estateModel)->post(route('manage.billing.submissions.approve', PaymentSubmission::first()), ['amount' => '250000']);
        $payment = PaymentSubmission::first()->payment;

        $this->inEstate($this->resident, $this->estateModel)->get(route('transactions.index'))->assertOk()
            ->assertSee('Estate management &amp; security levy', false)->assertSee($payment->reference);
        $this->inEstate($this->resident, $this->estateModel)->get(route('transactions.index', ['tab' => 'invoices']))->assertOk()->assertSee($this->levy->number);

        $pdf = $this->inEstate($this->resident, $this->estateModel)->get(route('pdf.invoice', $this->levy));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString($this->levy->number.'.pdf', $pdf->headers->get('Content-Disposition'));

        $this->inEstate($this->resident, $this->estateModel)->get(route('pdf.payment', $payment))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        [$neighbour] = $this->resident($this->estateModel);
        $this->inEstate($neighbour, $this->estateModel)->get(route('pdf.invoice', $this->levy))->assertForbidden();
        $this->inEstate($neighbour, $this->estateModel)->get(route('pdf.payment', $payment))->assertForbidden();
        $this->inEstate($neighbour, $this->estateModel)->get(route('transactions.index'))->assertOk()->assertDontSee($payment->reference);
    }
}
