<?php

namespace App\Enums;

enum AnnouncementCategory: string
{
    case General = 'general';
    case Maintenance = 'maintenance';
    case UtilityInterruption = 'utility_interruption';
    case Emergency = 'emergency';
    case Event = 'event';
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Maintenance => 'Maintenance',
            self::UtilityInterruption => 'Utility interruption',
            self::Emergency => 'Emergency',
            self::Event => 'Community event',
            self::Security => 'Security',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::General => 'neutral',
            self::Maintenance => 'info',
            self::UtilityInterruption => 'warning',
            self::Emergency => 'danger',
            self::Event => 'brand',
            self::Security => 'warning',
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
