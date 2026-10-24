<?php

namespace App\Http\Middleware;

use App\Services\Billing\LevyLockout;
use App\Support\EstateContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends residents with an overdue yearly levy to the lock screen. Staff and platform admins are never
 * locked; locked residents can still open their levy bill, pay it online, upload a bank receipt and
 * download invoices or receipts.
 */
class EnsureLevyPaid
{
    private const ALLOWED_ROUTES = [
        'levy.locked',
        'bills.show',
        'bills.pay',
        'bills.receipts.store',
        'bills.payments.*',
        'bills.receipt',
        'pdf.invoice',
        'pdf.payment',
    ];

    public function __construct(private EstateContext $context, private LevyLockout $lockout) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->context->hasEstate() || $user->isPlatformAdmin() || $this->context->isStaff() || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($this->lockout->isLocked($user, $this->context->estate())) {
            return redirect()->route('levy.locked');
        }

        return $next($request);
    }
}
