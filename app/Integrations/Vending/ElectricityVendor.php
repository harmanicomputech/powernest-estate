<?php

namespace App\Integrations\Vending;

interface ElectricityVendor
{
    public function isConfigured(): bool;

    /**
     * Returns null when the vendor does not recognise the meter.
     */
    public function lookupMeter(string $meterNumber): ?MeterInfo;

    /**
     * Vend electricity by amount (kobo). Must never throw for transport errors: they are
     * classified into VendOutcome so the caller can apply the correct recovery rule.
     */
    public function vend(string $meterNumber, int $amount, string $reference): VendResult;
}
