<?php

namespace App\Services\Power;

use App\Integrations\IntegrationException;
use App\Integrations\Vending\ElectricityVendor;
use App\Models\Estate;
use App\Models\Meter;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class MeterService
{
    public function __construct(private ElectricityVendor $vendor, private AuditLogger $audit) {}

    /**
     * Registers a meter against a property the user is linked to. The vendor lookup is used when
     * reachable; an unreachable vendor leaves the meter marked unverified (never faked as valid).
     */
    public function register(User $user, Estate $estate, int $propertyId, string $meterNumber, ?string $label): Meter
    {
        $property = $user->authorizedProperties($estate)->whereKey($propertyId)->first();

        if (! $property) {
            throw ValidationException::withMessages(['property_id' => 'Choose a property you are linked to.']);
        }

        $meterNumber = preg_replace('/\s+/', '', $meterNumber);
        $existing = Meter::where('estate_id', $estate->id)->where('meter_number', $meterNumber)->first();

        if ($existing && $existing->property_id !== $property->id) {
            throw ValidationException::withMessages(['meter_number' => 'This meter is registered to another property. Contact estate management if this is wrong.']);
        }

        if ($existing) {
            return $existing;
        }

        $info = null;
        $verified = false;

        try {
            $info = $this->vendor->lookupMeter($meterNumber);

            if ($info === null) {
                throw ValidationException::withMessages(['meter_number' => 'This meter number was not recognised by the vending service.']);
            }

            $verified = true;
        } catch (IntegrationException) {
            // Vendor unreachable or disabled: save as unverified.
        }

        $meter = Meter::create([
            'estate_id' => $estate->id,
            'property_id' => $property->id,
            'meter_number' => $meterNumber,
            'label' => $label ?: $property->identifier,
            'meter_type' => $info?->meterType,
            'vendor_customer_name' => $info?->customerName,
            'validated_at' => $verified ? now() : null,
            'created_by' => $user->id,
        ]);

        $this->audit->record('meter.registered', $meter, ['verified' => $verified], $estate, $user);

        return $meter;
    }
}
