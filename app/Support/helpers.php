<?php

use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Support\Carbon;

if (! function_exists('money')) {
    function money(?int $kobo, bool $showKobo = true): string
    {
        return Money::format($kobo, showKobo: $showKobo);
    }
}

if (! function_exists('local_time')) {
    /**
     * Format a UTC timestamp in the current estate's timezone.
     */
    function local_time(?Carbon $time, string $format = 'j M Y, g:ia'): string
    {
        if (! $time) {
            return '—';
        }

        $tz = app(EstateContext::class)->estateOrNull()?->timezone() ?? config('powernest.display_timezone');

        return $time->copy()->timezone($tz)->format($format);
    }
}

if (! function_exists('humanize')) {
    function humanize(?string $value): string
    {
        return $value ? ucfirst(str_replace('_', ' ', $value)) : '';
    }
}
