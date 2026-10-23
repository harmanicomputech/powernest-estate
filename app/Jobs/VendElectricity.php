<?php

namespace App\Jobs;

use App\Models\PowerPurchase;
use App\Services\Power\PowerPurchaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Requests the token for a paid purchase. Single attempt per job: retries are decided by the
 * service according to the vend outcome, never by the queue. Duplicate jobs are harmless because
 * PowerPurchaseService::vend() only proceeds from "paid" under a row lock.
 */
class VendElectricity implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $purchaseId) {}

    public function handle(PowerPurchaseService $service): void
    {
        $purchase = PowerPurchase::find($this->purchaseId);

        if ($purchase) {
            $service->vend($purchase);
        }
    }
}
