<?php

namespace App\Enums;

enum PassType: string
{
    case OneTime = 'one_time';
    case MultiEntry = 'multi_entry';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-time',
            self::MultiEntry => 'Multi-entry',
            self::Recurring => 'Recurring',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::OneTime => 'neutral',
            self::MultiEntry => 'info',
            self::Recurring => 'brand',
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
