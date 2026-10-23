<?php

namespace App\Jobs;

use App\Services\Payments\PaymentReferenceRouter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Triggered by webhooks: the webhook only tells us *which* reference to check.
 */
class VerifyGatewayPayment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public int $uniqueFor = 60;

    public function __construct(public string $gateway, public string $reference) {}

    public function uniqueId(): string
    {
        return $this->gateway.':'.$this->reference;
    }

    public function handle(PaymentReferenceRouter $router): void
    {
        $router->verify($this->gateway, $this->reference, 'webhook');
    }
}
