<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is stored and computed as integer kobo. Never use floats for amounts.
 */
final class Money
{
    public static function format(?int $kobo, string $currency = 'NGN', bool $showKobo = true): string
    {
        $kobo ??= 0;
        $symbol = $currency === 'NGN' ? '₦' : $currency.' ';
        $negative = $kobo < 0;
        $kobo = abs($kobo);
        $naira = intdiv($kobo, 100);
        $fraction = str_pad((string) ($kobo % 100), 2, '0', STR_PAD_LEFT);
        $formatted = number_format($naira).($showKobo ? '.'.$fraction : '');

        return ($negative ? '-' : '').$symbol.$formatted;
    }

    public static function compact(?int $kobo): string
    {
        $naira = intdiv($kobo ?? 0, 100);

        return match (true) {
            $naira >= 1_000_000_000 => '₦'.rtrim(rtrim(number_format($naira / 1_000_000_000, 1), '0'), '.').'B',
            $naira >= 1_000_000 => '₦'.rtrim(rtrim(number_format($naira / 1_000_000, 1), '0'), '.').'M',
            $naira >= 10_000 => '₦'.rtrim(rtrim(number_format($naira / 1_000, 1), '0'), '.').'K',
            default => '₦'.number_format($naira),
        };
    }

    /**
     * Parse a user-entered naira amount ("12,500.50") into kobo using string arithmetic.
     */
    public static function parse(string|int $input): int
    {
        $clean = str_replace([',', ' ', '₦'], '', trim((string) $input));

        if (! preg_match('/^\d{1,12}(\.\d{1,2})?$/', $clean)) {
            throw new InvalidArgumentException('Invalid amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $clean), 2, '0');

        return ((int) $whole) * 100 + (int) str_pad($fraction, 2, '0');
    }

    /**
     * Decimal naira string for provider APIs that take major units ("2500.00").
     */
    public static function toDecimalString(int $kobo): string
    {
        return intdiv($kobo, 100).'.'.str_pad((string) ($kobo % 100), 2, '0', STR_PAD_LEFT);
    }
}
