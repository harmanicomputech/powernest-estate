<?php

namespace App\Integrations;

/**
 * Single choke point that prevents any live provider call outside production.
 */
final class OutboundGuard
{
    public static function ensureLive(string $provider): void
    {
        if (! config('powernest.integrations_live')) {
            throw IntegrationException::disabled($provider);
        }
    }
}
