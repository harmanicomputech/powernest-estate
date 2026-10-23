<?php

namespace App\Enums;

enum VisitStatus: string
{
    case AwaitingHost = 'awaiting_host';
    case Inside = 'inside';
    case Exited = 'exited';
    case Denied = 'denied';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingHost => 'Awaiting host',
            self::Inside => 'Inside',
            self::Exited => 'Checked out',
            self::Denied => 'Denied',
            self::Declined => 'Host declined',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::AwaitingHost => 'warning',
            self::Inside => 'success',
            self::Exited => 'neutral',
            self::Denied => 'danger',
            self::Declined => 'danger',
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
