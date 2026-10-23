<?php

namespace App\Integrations\Vending;

final readonly class MeterInfo
{
    public function __construct(
        public string $meterNumber,
        public ?string $customerName,
        public ?string $meterType,
        public ?string $address = null,
    ) {}
}
