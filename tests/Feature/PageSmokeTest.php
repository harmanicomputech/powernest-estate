<?php

namespace Tests\Feature;

use App\Models\Estate;
use App\Models\Facility;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\PowerPurchase;
use App\Models\Property;
use App\Models\User;
use App\Models\VisitorPass;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every screen for every demo role against the seeded demo estate.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_render_its_pages(): void
    {
        $this->seed(DemoSeeder::class);

        $pass = VisitorPass::first();
        $purchase = PowerPurchase::where('user_id', User::where('email', 'resident@powernest.test')->value('id'))->first();
        $invoice = Invoice::first();
        $payment = Payment::where('status', 'succeeded')->first();

        $common = ['/app', '/app/announcements', '/notifications', '/account'];
        $pages = [
            'resident@powernest.test' => [...$common, '/app/home', '/app/visitors', '/app/visitors?tab=history', '/app/visitors/new', '/app/visitors/'.$pass->id, '/app/power', '/app/power/buy', '/app/power/history', '/app/power/purchases/'.$purchase->reference, '/app/bills', '/app/maintenance', '/app/maintenance/new', '/app/facilities', '/app/facilities/bookings', '/app/facilities/'.Facility::first()->id, '/app/incidents', '/app/incidents/new', '/app/lost-found', '/app/transactions', '/app/transactions?tab=invoices', '/app/transactions?tab=electricity'],
            'guard@powernest.test' => [...$common, '/app/inspections', '/app/lost-found', '/app/gate', '/app/gate/expected', '/app/gate/inside', '/app/gate/walk-in', '/app/incidents', '/app/tasks'],
            'supervisor@powernest.test' => [...$common, '/app/gate', '/app/manage/visits', '/app/incidents', '/app/incidents/'.Incident::first()->reference, '/app/tasks'],
            'facility@powernest.test' => [...$common, '/app/maintenance', '/app/maintenance?tab=overdue', '/app/maintenance/'.MaintenanceRequest::first()->reference, '/app/manage/assets', '/app/manage/bookings', '/app/manage/expenses', '/app/tasks'],
            'tech@powernest.test' => [...$common, '/app/maintenance', '/app/maintenance?tab=mine', '/app/tasks'],
            'finance@powernest.test' => [...$common, '/app/manage/billing', '/app/manage/billing/invoices', '/app/manage/billing/invoices?status=paid', '/app/manage/billing/invoices/new', '/app/bills/'.$invoice->number, '/app/payments/'.$payment->reference.'/receipt', '/app/manage/power', '/app/manage/power?status=issued', '/app/manage/power/'.$purchase->reference, '/app/manage/expenses', '/app/manage/support', '/app/manage/reports', '/app/manage/billing/receipts', '/app/manage/billing/receipts?status=approved', '/app/manage/billing/receipts/'.PaymentSubmission::where('status', 'pending')->value('reference')],
            'manager@powernest.test' => [...$common, '/app/manage/properties', '/app/manage/properties/new', '/app/manage/properties/'.Property::first()->id, '/app/manage/residents', '/app/manage/members', '/app/manage/members?tab=invitations', '/app/manage/members?tab=requests', '/app/manage/visits', '/app/manage/power', '/app/manage/billing', '/app/manage/reports', '/app/manage/audit', '/app/manage/settings', '/app/announcements/new', '/app/gate'],
            'platform@powernest.test' => ['/platform', '/platform/estates/create', '/platform/estates/'.Estate::first()->id, '/onboarding'],
        ];

        $failures = [];

        foreach ($pages as $email => $urls) {
            $user = User::where('email', $email)->firstOrFail();

            foreach ($urls as $url) {
                $response = $this->actingAs($user)->get($url);

                if ($response->status() !== 200) {
                    $failures[] = "{$email} {$url} → {$response->status()} ".mb_substr((string) ($response->exception?->getMessage() ?? ''), 0, 300);
                }
            }

            $this->app['auth']->forgetGuards();
            app()->forgetScopedInstances();
        }

        $this->get('/p/'.$pass->token())->assertOk();
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();

        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
