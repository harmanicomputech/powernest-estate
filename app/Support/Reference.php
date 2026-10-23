<?php

namespace App\Support;

/**
 * Unpredictable, human-friendly references (no ambiguous characters).
 */
final class Reference
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function make(string $prefix, int $length = 10): string
    {
        $chars = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return $prefix.'-'.$chars;
    }

    public static function numericCode(int $digits = 6): string
    {
        return str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
    }
}
