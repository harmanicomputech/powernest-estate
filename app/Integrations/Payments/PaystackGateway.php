<?php

namespace App\Integrations\Payments;

use App\Integrations\IntegrationException;
use App\Integrations\OutboundGuard;
use App\Support\Redactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Paystack Transactions API: initialize (redirect checkout), verify, HMAC-SHA512 webhooks.
 */
class PaystackGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    public function label(): string
    {
        return 'Paystack';
    }

    public function isAvailable(): bool
    {
        return (bool) config('services.paystack.enabled') && filled(config('services.paystack.secret_key'));
    }

    public function initialize(PaymentIntent $intent): CheckoutSession
    {
        $response = $this->client()->post('/transaction/initialize', [
            'email' => $intent->email,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'reference' => $intent->reference,
            'callback_url' => $intent->callbackUrl,
            'metadata' => $intent->metadata + ['description' => $intent->description],
        ]);

        $url = $response->json('data.authorization_url');

        if (! $response->successful() || $response->json('status') !== true || ! is_string($url)) {
            throw new IntegrationException('Paystack could not start the payment: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return CheckoutSession::redirect($url);
    }

    public function verify(string $reference): PaymentVerification
    {
        try {
            $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));
        } catch (ConnectionException $e) {
            throw new IntegrationException('Paystack verification is unreachable.', previous: $e);
        }

        if ($response->status() === 404 || ($response->json('status') === false && str_contains(strtolower((string) $response->json('message')), 'not found'))) {
            return new PaymentVerification($this->name(), $reference, VerificationStatus::NotFound, message: 'Transaction not found');
        }

        if (! $response->successful() || $response->json('status') !== true) {
            throw new IntegrationException('Paystack verification failed: HTTP '.$response->status());
        }

        $data = (array) $response->json('data');

        $status = match ($data['status'] ?? null) {
            'success' => VerificationStatus::Succeeded,
            'failed', 'abandoned', 'reversed' => VerificationStatus::Failed,
            default => VerificationStatus::Pending,
        };

        return new PaymentVerification(
            gateway: $this->name(),
            reference: (string) ($data['reference'] ?? ''),
            status: $status,
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
            gatewayReference: isset($data['id']) ? (string) $data['id'] : null,
            channel: $data['channel'] ?? null,
            paidAt: ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
            message: $data['gateway_response'] ?? null,
            raw: Redactor::redact(array_intersect_key($data, array_flip(['id', 'status', 'reference', 'amount', 'currency', 'channel', 'paid_at', 'gateway_response', 'fees']))),
        );
    }

    public function webhookSignatureValid(Request $request): bool
    {
        $secret = (string) config('services.paystack.secret_key');
        $signature = (string) $request->header('x-paystack-signature');

        return $secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature);
    }

    public function webhookReference(array $payload): ?string
    {
        $reference = data_get($payload, 'data.reference');

        return is_string($reference) ? $reference : null;
    }

    private function client(): PendingRequest
    {
        OutboundGuard::ensureLive('Paystack');

        if (! filled(config('services.paystack.secret_key'))) {
            throw IntegrationException::notConfigured('Paystack');
        }

        return Http::baseUrl(config('services.paystack.base_url'))
            ->withToken(config('services.paystack.secret_key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);
    }
}
