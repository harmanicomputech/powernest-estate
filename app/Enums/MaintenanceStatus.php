<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Submitted = 'submitted';
    case Assigned = 'assigned';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Assigned => 'Assigned',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In progress',
            self::OnHold => 'On hold',
            self::Completed => 'Awaiting confirmation',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'neutral',
            self::Assigned => 'info',
            self::Scheduled => 'info',
            self::InProgress => 'brand',
            self::OnHold => 'warning',
            self::Completed => 'success',
            self::Closed => 'neutral',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Assigned, self::Scheduled, self::InProgress, self::OnHold, self::Cancelled],
            self::Assigned => [self::Scheduled, self::InProgress, self::OnHold, self::Cancelled, self::Assigned],
            self::Scheduled => [self::InProgress, self::OnHold, self::Assigned, self::Cancelled],
            self::InProgress => [self::OnHold, self::Completed, self::Assigned],
            self::OnHold => [self::Assigned, self::Scheduled, self::InProgress, self::Cancelled],
            self::Completed => [self::Closed, self::InProgress],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Closed, self::Cancelled], true);
    }
}
