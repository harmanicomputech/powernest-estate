<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PowerPurchaseStatus;
use App\Http\Controllers\Controller;
use App\Integrations\Payments\GatewayManager;
use App\Integrations\Vending\ElectricityVendor;
use App\Models\AuditLog;
use App\Models\Estate;
use App\Models\GatewayWebhookEvent;
use App\Models\PowerPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Platform-level view. Shows aggregate health only: no resident, visitor or financial detail
 * from inside an estate unless the admin is also a member of that estate.
 */
class PlatformController extends Controller
{
    public function dashboard(GatewayManager $gateways, ElectricityVendor $vendor): View
    {
        $since = now()->subDay();

        return view('platform.dashboard', [
            'estates' => Estate::withCount(['properties', 'activeMemberships'])->orderBy('name')->get(),
            'users' => User::count(),
            'integrations' => [
                ['name' => 'Outbound integrations', 'ok' => (bool) config('powernest.integrations_live'), 'detail' => config('powernest.integrations_live') ? 'Live calls enabled' : 'Disabled in this environment (INTEGRATIONS_LIVE=false)'],
                ['name' => 'Stron vending', 'ok' => $vendor->isConfigured(), 'detail' => $vendor->isConfigured() ? 'Credentials configured' : 'Missing STRON_* configuration'],
                ['name' => 'Payaza checkout', 'ok' => $gateways->get('payaza')->isAvailable(), 'detail' => $gateways->get('payaza')->isAvailable() ? 'Public key configured' : 'Disabled or missing PAYAZA_PUBLIC_KEY'],
                ['name' => 'Payaza verification', 'ok' => filled(config('services.payaza.verify_url')), 'detail' => filled(config('services.payaza.verify_url')) ? 'Status query endpoint configured' : 'PAYAZA_VERIFY_URL not set — payments need manual confirmation'],
                ['name' => 'Paystack', 'ok' => $gateways->get('paystack')->isAvailable(), 'detail' => $gateways->get('paystack')->isAvailable() ? 'Secret key configured' : 'Disabled or missing PAYSTACK_SECRET_KEY'],
                ['name' => 'Queue', 'ok' => config('queue.default') !== 'sync', 'detail' => 'Driver: '.config('queue.default').' · failed jobs: '.DB::table('failed_jobs')->count()],
            ],
            'power' => [
                'issued24h' => PowerPurchase::where('status', PowerPurchaseStatus::TokenIssued->value)->where('token_issued_at', '>=', $since)->count(),
                'attention' => PowerPurchase::whereIn('status', [PowerPurchaseStatus::VendFailed->value, PowerPurchaseStatus::ReconciliationRequired->value])->count(),
                'pending' => PowerPurchase::where('status', PowerPurchaseStatus::PendingPayment->value)->where('created_at', '>=', $since)->count(),
                'webhooks24h' => GatewayWebhookEvent::where('created_at', '>=', $since)->count(),
                'badSignatures' => GatewayWebhookEvent::where('created_at', '>=', $since)->where('signature_valid', false)->where('gateway', 'paystack')->count(),
            ],
            'audit' => AuditLog::whereNull('estate_id')->orWhereIn('action', ['estate.created', 'estate.status_changed'])->with('user')->latest('id')->limit(10)->get(),
        ]);
    }
}
