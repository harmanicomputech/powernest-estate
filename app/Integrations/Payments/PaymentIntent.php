<?php

namespace App\Integrations\Payments;

final readonly class PaymentIntent
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public string $email,
        public string $customerName,
        public ?string $phone,
        public string $callbackUrl,
        public string $description,
        public array $metadata = [],
    ) {}
}
