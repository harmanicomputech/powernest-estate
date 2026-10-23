<?php

namespace App\Http\Controllers;

use App\Integrations\Payments\GatewayManager;
use App\Jobs\VerifyGatewayPayment;
use App\Models\GatewayWebhookEvent;
use App\Support\Redactor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhooks never confirm a payment by themselves. They are stored once (fingerprint dedupe)
 * and trigger a server-to-server verification of the referenced transaction.
 */
class WebhookController extends Controller
{
    public function __construct(private GatewayManager $gateways) {}

    public function paystack(Request $request): JsonResponse
    {
        $gateway = $this->gateways->get('paystack');

        if (! $gateway->webhookSignatureValid($request)) {
            Log::warning('Rejected Paystack webhook with invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = (array) $request->json()->all();
        $process = ($payload['event'] ?? null) === 'charge.success';

        return $this->record('paystack', $request, $payload, true, $process ? $gateway->webhookReference($payload) : null, $payload['event'] ?? null);
    }

    public function payaza(Request $request, ?string $secret = null): JsonResponse
    {
        $gateway = $this->gateways->get('payaza');
        $configured = filled(config('services.payaza.webhook_secret'));

        // When a URL secret is configured, requests without it are rejected outright.
        if ($configured && ! $gateway->webhookSignatureValid($request)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $payload = (array) $request->json()->all();

        return $this->record('payaza', $request, $payload, $configured, $gateway->webhookReference($payload), $payload['transaction_status'] ?? $payload['event'] ?? null);
    }

    private function record(string $gateway, Request $request, array $payload, bool $signatureValid, ?string $reference, ?string $type): JsonResponse
    {
        try {
            $event = GatewayWebhookEvent::create([
                'gateway' => $gateway,
                'fingerprint' => hash('sha256', $gateway.'|'.$request->getContent()),
                'reference' => $reference,
                'event_type' => $type ? mb_substr((string) $type, 0, 60) : null,
                'signature_valid' => $signatureValid,
                'payload' => Redactor::redact($payload),
                'status' => $reference ? 'queued' : 'ignored',
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Duplicate ignored']);
        }

        if ($reference) {
            VerifyGatewayPayment::dispatch($gateway, $reference);
            $event->update(['status' => 'processed', 'processed_at' => now()]);
        }

        return response()->json(['message' => 'Received']);
    }
}
