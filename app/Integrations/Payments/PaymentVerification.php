<?php

namespace App\Integrations\Payments;

use Illuminate\Support\Carbon;

/**
 * Normalised, server-side result of asking a gateway about a reference.
 */
final readonly class PaymentVerification
{
    /**
     * @param  array<string, mixed>  $raw  Already redacted provider payload.
     */
    public function __construct(
        public string $gateway,
        public string $reference,
        public VerificationStatus $status,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $gatewayReference = null,
        public ?string $channel = null,
        public ?Carbon $paidAt = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    public function succeeded(): bool
    {
        return $this->status === VerificationStatus::Succeeded;
    }
}
