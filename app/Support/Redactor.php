<?php

namespace App\Support;

/**
 * Removes secrets and sensitive values from arrays before they are logged or persisted.
 */
final class Redactor
{
    /**
     * Keys are matched case-insensitively after stripping non-alphanumerics.
     *
     * @var list<string>
     */
    private const SENSITIVE = [
        'password', 'passwd', 'pass', 'secret', 'secretkey', 'apikey', 'authorization', 'token', 'accesstoken',
        'merchantkey', 'pin', 'cvv', 'cardnumber', 'pan', 'authorizationcode', 'signature', 'otp', 'bvn',
    ];

    /**
     * Keys containing personal data that are masked rather than dropped.
     *
     * @var list<string>
     */
    private const MASKED = ['accountnumber', 'accountname', 'email', 'emailaddress', 'phone', 'phonenumber', 'mobilenumber', 'customerphone'];

    public static function redact(mixed $data, int $depth = 0): mixed
    {
        if (! is_array($data) || $depth > 8) {
            return $data;
        }

        $out = [];

        foreach ($data as $key => $value) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));

            if (in_array($normalized, self::SENSITIVE, true)) {
                $out[$key] = '[REDACTED]';
            } elseif (in_array($normalized, self::MASKED, true) && is_scalar($value)) {
                $out[$key] = self::mask((string) $value);
            } else {
                $out[$key] = self::redact($value, $depth + 1);
            }
        }

        return $out;
    }

    public static function mask(string $value): string
    {
        $length = mb_strlen($value);

        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return mb_substr($value, 0, 2).str_repeat('•', max(0, $length - 4)).mb_substr($value, -2);
    }
}
