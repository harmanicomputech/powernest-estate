<?php

namespace App\Integrations\Payments;

/**
 * How the browser should continue to the provider: a redirect URL or an inline SDK configuration
 * containing only public values.
 */
final readonly class CheckoutSession
{
    /**
     * @param  array<string, mixed>  $inline
     */
    public function __construct(
        public string $mode,
        public ?string $redirectUrl = null,
        public array $inline = [],
        public ?string $script = null,
    ) {}

    public static function redirect(string $url): self
    {
        return new self('redirect', redirectUrl: $url);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function inline(string $script, array $config): self
    {
        return new self('inline', inline: $config, script: $script);
    }
}
