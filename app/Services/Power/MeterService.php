<?php

namespace App\Services\Power;

use App\Integrations\IntegrationException;
use App\Integrations\Vending\ElectricityVendor;
use App\Models\Estate;
use App\Models\Meter;
use App\Models\PowerPurchase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MeterService
{
    public function __construct(private ElectricityVendor $vendor, private AuditLogger $audit) {}

    /**
     * Finds a meter in the estate by its number, checking it with the vending service the first time
     * (or when it was never verified). Returns only verified meters: an unknown number or an
     * unreachable vendor is reported to the user, never saved as valid. The meter is added to the
     * user's saved meters so it appears next time.
     */
    public function lookup(User $user, Estate $estate, string $meterNumber): Meter
    {
        $meterNumber = preg_replace('/\D/', '', $meterNumber);
        $meter = Meter::where('estate_id', $estate->id)->where('meter_number', $meterNumber)->first();

        if ($meter && $meter->status !== 'active') {
            throw ValidationException::withMessages(['meter_number' => 'This meter has been disabled by the estate. Contact the estate office.']);
        }

        if (! $meter?->validated_at) {
            try {
                $info = $this->vendor->lookupMeter($meterNumber);
            } catch (IntegrationException) {
                throw ValidationException::withMessages(['meter_number' => 'We could not reach the meter service to check this number. Please try again in a minute.']);
            }

            if ($info === null) {
                throw ValidationException::withMessages(['meter_number' => 'This meter number was not recognised. Check the number on the front of the meter.']);
            }

            $meter ??= new Meter(['estate_id' => $estate->id, 'meter_number' => $meterNumber, 'created_by' => $user->id]);
            $meter->fill([
                'meter_type' => $info->meterType ?? $meter->meter_type,
                'vendor_customer_name' => $info->customerName ?? $meter->vendor_customer_name,
                'vendor_address' => $info->address ?? $meter->vendor_address,
                'validated_at' => now(),
            ]);
            $isNew = ! $meter->exists;
            $meter->save();

            $this->audit->record($isNew ? 'meter.registered' : 'meter.verified', $meter, ['verified' => true], $estate, $user);
        }

        $user->favouriteMeters()->syncWithoutDetaching([$meter->id]);

        return $meter;
    }

    /**
     * Meters the user has checked before, bought for, or that are on their home.
     *
     * @return Collection<int, Meter>
     */
    public function savedFor(User $user, Estate $estate): Collection
    {
        $ids = $user->favouriteMeters()->where('meters.estate_id', $estate->id)->pluck('meters.id')
            ->merge(PowerPurchase::forEstate($estate)->where('user_id', $user->id)->latest()->limit(50)->pluck('meter_id'))
            ->merge(Meter::forEstate($estate)->whereIn('property_id', $user->authorizedProperties($estate)->pluck('properties.id'))->pluck('id'))
            ->unique();

        return Meter::forEstate($estate)->whereIn('id', $ids)->where('status', 'active')->with('property')->orderBy('meter_number')->get();
    }
}
