<?php

namespace App\Integrations\Vending;

final readonly class VendResult
{
    /**
     * @param  array<string, mixed>  $raw  Redacted provider response.
     */
    public function __construct(
        public VendOutcome $outcome,
        public ?string $token = null,
        public ?string $units = null,
        public ?string $unitLabel = null,
        public ?string $vendorReference = null,
        public ?int $httpStatus = null,
        public ?string $error = null,
        public array $raw = [],
    ) {}
}
