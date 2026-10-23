<?php

namespace App\Enums;

enum OccupancyStatus: string
{
    case Vacant = 'vacant';
    case OwnerOccupied = 'owner_occupied';
    case Tenanted = 'tenanted';
    case UnderRenovation = 'under_renovation';

    public function label(): string
    {
        return match ($this) {
            self::Vacant => 'Vacant',
            self::OwnerOccupied => 'Owner occupied',
            self::Tenanted => 'Tenanted',
            self::UnderRenovation => 'Under renovation',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Vacant => 'neutral',
            self::OwnerOccupied => 'brand',
            self::Tenanted => 'info',
            self::UnderRenovation => 'warning',
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
