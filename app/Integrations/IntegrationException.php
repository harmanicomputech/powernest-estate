<?php

namespace App\Integrations;

use RuntimeException;

class IntegrationException extends RuntimeException
{
    public static function disabled(string $provider): self
    {
        return new self("{$provider} requests are disabled in this environment (INTEGRATIONS_LIVE=false).");
    }

    public static function notConfigured(string $provider): self
    {
        return new self("{$provider} is not configured.");
    }
}
