<?php

namespace App\Services\Visitors;

use App\Models\VisitorPass;

/**
 * Outcome of a server-side pass verification.
 */
final readonly class PassCheck
{
    public function __construct(
        public bool $valid,
        public string $result,
        public string $message,
        public ?VisitorPass $pass = null,
    ) {}

    public static function ok(VisitorPass $pass): self
    {
        return new self(true, 'valid', 'Pass is valid. Confirm the visitor and admit.', $pass);
    }

    public static function fail(string $result, string $message, ?VisitorPass $pass = null): self
    {
        return new self(false, $result, $message, $pass);
    }
}
