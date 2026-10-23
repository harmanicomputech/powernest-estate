<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Unpaid',
            self::PartiallyPaid => 'Part paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Issued => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
            self::Void => 'neutral',
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
