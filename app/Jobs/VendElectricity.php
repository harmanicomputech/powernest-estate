<?php

namespace App\Jobs;

use App\Models\PowerPurchase;
use App\Services\Power\PowerPurchaseService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Requests the token for a paid purchase. Single attempt per job: retries are decided by the
 * service according to the vend outcome, never by the queue.
 */
class VendElectricity implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public int $purchaseId) {}

    public function uniqueId(): string
    {
        return (string) $this->purchaseId;
    }

    public function handle(PowerPurchaseService $service): void
    {
        $purchase = PowerPurchase::find($this->purchaseId);

        if ($purchase) {
            $service->vend($purchase);
        }
    }
}
