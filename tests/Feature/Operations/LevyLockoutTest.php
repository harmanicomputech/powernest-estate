<?php

namespace Tests\Feature\Operations;

use App\Enums\EstateRole;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsEstates;
use Tests\TestCase;

class LevyLockoutTest extends TestCase
{
    use BuildsEstates, RefreshDatabase;

    private Estate $estateModel;

    private User $resident;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');

        $this->estateModel = $this->estate();
        [$this->resident] = $this->resident($this->estateModel);
        $this->finance = $this->staff($this->estateModel, EstateRole::FinanceOfficer);
    }

    private function issueLevy(int $dueDaysAgo): Invoice
    {
        $charge = ChargeType::create(['estate_id' => $this->estateModel->id, 'name' => 'Estate management & security levy', 'code' => 'LEVY', 'amount' => 100_000_000, 'frequency' => 'annual']);
        $year = now('Africa/Lagos')->startOfYear();
        app(BillingService::class)->runCharge($charge, $year, $year->copy()->endOfYear(), now()->subDays($dueDaysAgo), $this->finance);

        return Invoice::firstOrFail();
    }

    public function test_within_grace_period_resident_keeps_access_and_sees_lock_date(): void
    {
        $this->issueLevy(dueDaysAgo: 10);

        $this->inEstate($this->resident, $this->estateModel)->get(route('dashboard'))
            ->assertOk()->assertSee('App access is paused from');
    }

    public function test_overdue_levy_locks_resident_until_receipt_is_approved(): void
    {
        $invoice = $this->issueLevy(dueDaysAgo: 40);

        $this->inEstate($this->resident, $this->estateModel)->get(route('dashboard'))->assertRedirect(route('levy.locked'));
        $this->inEstate($this->resident, $this->estateModel)->get(route('power.index'))->assertRedirect(route('levy.locked'));
        $this->inEstate($this->resident, $this->estateModel)->get(route('levy.locked'))->assertOk()->assertSee('Your access is on hold')->assertSee('₦1,000,000.00');

        // While locked they can still open the bill and upload an offline receipt.
        $this->inEstate($this->resident, $this->estateModel)->get(route('bills.show', $invoice))->assertOk();
        $this->inEstate($this->resident, $this->estateModel)->post(route('bills.receipts.store', $invoice), [
            'amount' => '1,000,000', 'method' => 'bank_transfer', 'payer_reference' => 'GTB-1', 'paid_on' => now()->toDateString(),
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertSessionHasNoErrors();
        $this->inEstate($this->resident, $this->estateModel)->get(route('levy.locked'))->assertSee('Receipt under review');

        // Staff are never locked.
        $this->inEstate($this->finance, $this->estateModel)->get(route('dashboard'))->assertOk();

        app(PaymentSubmissionService::class)->approve(PaymentSubmission::firstOrFail(), $this->finance, 100_000_000, 'Matched statement');

        $this->inEstate($this->resident, $this->estateModel)->get(route('dashboard'))->assertOk()->assertSee('paid for');
        $this->inEstate($this->resident, $this->estateModel)->get(route('levy.locked'))->assertRedirect(route('dashboard'));
    }

    public function test_lockout_can_be_switched_off_and_amount_syncs_the_levy_charge(): void
    {
        $this->issueLevy(dueDaysAgo: 40);
        $owner = $this->staff($this->estateModel, EstateRole::EstateOwner);

        $this->inEstate($owner, $this->estateModel)->put(route('manage.settings.update'), [
            'name' => $this->estateModel->name, 'timezone' => 'Africa/Lagos', 'service_charge' => '350', 'min_amount' => '1000', 'max_amount' => '50000',
            'pass_categories' => ['guest'], 'max_pass_days' => 30, 'levy_amount' => '1,200,000', 'levy_grace_days' => 30,
        ])->assertSessionHasNoErrors();

        $this->assertSame(120_000_000, ChargeType::where('code', 'LEVY')->value('amount'));
        $this->inEstate($this->resident, $this->estateModel)->get(route('dashboard'))->assertOk();
    }
}
