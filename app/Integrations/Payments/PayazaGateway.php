<?php

namespace App\Integrations\Payments;

use App\Integrations\IntegrationException;
use App\Integrations\OutboundGuard;
use App\Support\Money;
use App\Support\Redactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Payaza: inline checkout (public key only in the browser), server-side status query,
 * and webhooks that are treated as hints to re-verify (no signature scheme is configured).
 *
 * The status query endpoint is configurable (PAYAZA_VERIFY_URL) because its contract must be
 * confirmed against Payaza's current documentation before go-live. See docs/LEGACY_INVENTORY.md.
 */
class PayazaGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'payaza';
    }

    public function label(): string
    {
        return 'Payaza (card or bank transfer)';
    }

    public function isAvailable(): bool
    {
        return (bool) config('services.payaza.enabled') && filled(config('services.payaza.public_key'));
    }

    public function canVerify(): bool
    {
        return filled(config('services.payaza.verify_url'));
    }

    public function initialize(PaymentIntent $intent): CheckoutSession
    {
        if (! $this->isAvailable()) {
            throw IntegrationException::notConfigured('Payaza');
        }

        [$first, $last] = array_pad(explode(' ', trim($intent->customerName), 2), 2, '');

        // Only the public merchant key is exposed; the browser result is never trusted.
        return CheckoutSession::inline(config('services.payaza.checkout_script'), [
            'merchant_key' => config('services.payaza.public_key'),
            'connection_mode' => config('services.payaza.environment') === 'live' ? 'Live' : 'Test',
            'checkout_amount' => (float) Money::toDecimalString($intent->amount),
            'currency_code' => $intent->currency,
            'email_address' => $intent->email,
            'first_name' => $first ?: 'Customer',
            'last_name' => $last,
            'phone_number' => $intent->phone ?: '',
            'transaction_reference' => $intent->reference,
            'virtual_account_configuration' => ['expires_in_minutes' => 30],
        ]);
    }

    public function verify(string $reference): PaymentVerification
    {
        OutboundGuard::ensureLive('Payaza');

        if (! $this->canVerify()) {
            throw IntegrationException::notConfigured('Payaza status query (PAYAZA_VERIFY_URL)');
        }

        $url = str_replace('{reference}', rawurlencode($reference), (string) config('services.payaza.verify_url'));

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'Authorization' => 'Payaza '.base64_encode((string) config('services.payaza.public_key')),
                    'X-TenantID' => config('services.payaza.environment') === 'live' ? 'live' : 'test',
                ])
                ->connectTimeout(10)
                ->timeout(30)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new IntegrationException('Payaza status query is unreachable.', previous: $e);
        }

        if ($response->status() === 404) {
            return new PaymentVerification($this->name(), $reference, VerificationStatus::NotFound, message: 'Transaction not found');
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new IntegrationException('Payaza status query failed: HTTP '.$response->status());
        }

        $data = $this->unwrap((array) $response->json());

        return $this->fromPayload($reference, $data);
    }

    /**
     * Normalise a Payaza transaction payload (status query result or webhook body).
     *
     * @param  array<string, mixed>  $data
     */
    public function fromPayload(string $expectedReference, array $data): PaymentVerification
    {
        $statusText = Str::lower(trim((string) ($data['transaction_status'] ?? $data['status'] ?? '')));

        $status = match (true) {
            in_array($statusText, ['funds received', 'completed', 'successful', 'success', 'paid'], true) => VerificationStatus::Succeeded,
            in_array($statusText, ['failed', 'transaction failed', 'declined', 'reversed', 'cancelled', 'expired'], true) => VerificationStatus::Failed,
            default => VerificationStatus::Pending,
        };

        $amount = null;
        foreach (['amount_received', 'transaction_amount', 'amount'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                try {
                    $amount = Money::parse(number_format((float) $data[$key], 2, '.', ''));
                } catch (InvalidArgumentException) {
                    $amount = null;
                }
                break;
            }
        }

        $reference = (string) ($data['merchant_reference'] ?? $data['transaction_reference_merchant'] ?? $data['reference'] ?? '');

        return new PaymentVerification(
            gateway: $this->name(),
            reference: $reference !== '' ? $reference : $expectedReference,
            status: $status,
            amount: $amount,
            currency: isset($data['currency_code']) ? strtoupper((string) $data['currency_code']) : (isset($data['currency']) ? strtoupper((string) $data['currency']) : null),
            gatewayReference: isset($data['transaction_reference']) ? (string) $data['transaction_reference'] : null,
            channel: $data['channel'] ?? null,
            paidAt: ! empty($data['current_status_date']) ? Carbon::parse($data['current_status_date'], 'Africa/Lagos') : null,
            message: $data['status_reason'] ?? $data['transaction_status'] ?? null,
            raw: Redactor::redact(array_intersect_key($data, array_flip(['transaction_reference', 'merchant_reference', 'transaction_status', 'status', 'status_reason', 'amount_received', 'request_amount', 'transaction_fee', 'currency_code', 'channel', 'current_status_date', 'amount_validation']))),
        );
    }

    public function webhookSignatureValid(Request $request): bool
    {
        $secret = (string) config('services.payaza.webhook_secret');

        return $secret !== '' && hash_equals($secret, (string) $request->route('secret'));
    }

    public function webhookReference(array $payload): ?string
    {
        $data = $this->unwrap($payload);
        $reference = $data['merchant_reference'] ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrap(array $payload): array
    {
        foreach (['data', 'transaction'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key]) && ! array_is_list($payload[$key])) {
                return $payload[$key];
            }
        }

        return $payload;
    }
}
