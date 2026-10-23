<?php

namespace App\Integrations\Payments;

use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    public function label(): string;

    public function isAvailable(): bool;

    public function initialize(PaymentIntent $intent): CheckoutSession;

    /**
     * Ask the provider for the authoritative state of a payment.
     */
    public function verify(string $reference): PaymentVerification;

    /**
     * Whether an incoming webhook is authentic. Unsigned providers return false and their
     * webhooks are treated only as a prompt to re-verify.
     */
    public function webhookSignatureValid(Request $request): bool;

    /**
     * Extract our reference from a webhook payload, if present.
     *
     * @param  array<string, mixed>  $payload
     */
    public function webhookReference(array $payload): ?string;
}
