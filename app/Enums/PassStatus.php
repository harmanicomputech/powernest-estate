<?php

namespace App\Enums;

enum PassStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Consumed => 'Used',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Consumed => 'neutral',
            self::Expired => 'neutral',
            self::Revoked => 'danger',
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
