<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Duplex = 'duplex';
    case Terrace = 'terrace';
    case Apartment = 'apartment';
    case Commercial = 'commercial';
    case Land = 'land';

    public function label(): string
    {
        return match ($this) {
            self::House => 'House',
            self::Duplex => 'Duplex',
            self::Terrace => 'Terrace',
            self::Apartment => 'Apartment',
            self::Commercial => 'Commercial unit',
            self::Land => 'Plot / land',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::House => 'neutral',
            self::Duplex => 'neutral',
            self::Terrace => 'neutral',
            self::Apartment => 'neutral',
            self::Commercial => 'neutral',
            self::Land => 'neutral',
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
