<?php

namespace App\Enums;

enum OccupancyRelationship: string
{
    case Owner = 'owner';
    case Landlord = 'landlord';
    case Tenant = 'tenant';
    case HouseholdMember = 'household_member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Homeowner',
            self::Landlord => 'Landlord',
            self::Tenant => 'Tenant',
            self::HouseholdMember => 'Household member',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Owner => 'brand',
            self::Landlord => 'brand',
            self::Tenant => 'info',
            self::HouseholdMember => 'neutral',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
