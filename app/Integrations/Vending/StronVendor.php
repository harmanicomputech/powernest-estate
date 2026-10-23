<?php

namespace App\Integrations\Vending;

use App\Integrations\IntegrationException;
use App\Integrations\OutboundGuard;
use App\Support\Money;
use App\Support\Redactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stron prepaid vending API (contract taken from the legacy PowerNest production code):
 *   POST {base}/QueryMeterInfo  {CompanyName, UserName, PassWord, MeterId}
 *   POST {base}/VendingMeter    {CompanyName, UserName, PassWord, MeterID, is_vend_by_unit, Amount}
 * Both return a JSON array whose first element describes the meter / vend.
 */
class StronVendor implements ElectricityVendor
{
    public function isConfigured(): bool
    {
        return filled(config('services.stron.base_url')) && filled(config('services.stron.company'))
            && filled(config('services.stron.username')) && filled(config('services.stron.password'));
    }

    public function lookupMeter(string $meterNumber): ?MeterInfo
    {
        try {
            $response = $this->client(15)->post('/QueryMeterInfo', $this->credentials() + ['MeterId' => $meterNumber]);
        } catch (ConnectionException $e) {
            throw new IntegrationException('The meter service is unreachable. Try again shortly.', previous: $e);
        }

        if (! $response->successful()) {
            throw new IntegrationException('The meter service returned an error (HTTP '.$response->status().').');
        }

        $data = $response->json();
        $row = is_array($data) && array_is_list($data) ? ($data[0] ?? null) : ($data['MeterInfo'] ?? null);

        if (! is_array($row) || ! isset($row['Meter_id']) || (string) $row['Meter_id'] !== $meterNumber) {
            return null;
        }

        return new MeterInfo(
            meterNumber: (string) $row['Meter_id'],
            customerName: $row['Customer_name'] ?? null,
            meterType: $row['Meter_type'] ?? null,
            address: $row['Customer_address'] ?? null,
        );
    }

    public function vend(string $meterNumber, int $amount, string $reference): VendResult
    {
        try {
            $response = $this->client((int) config('services.stron.timeout', 45))->post('/VendingMeter', $this->credentials() + [
                'MeterID' => $meterNumber,
                'is_vend_by_unit' => 'false',
                'Amount' => (float) Money::toDecimalString($amount),
            ]);
        } catch (ConnectionException $e) {
            return new VendResult($this->classifyConnectionError($e), error: $this->safeMessage($e->getMessage()));
        } catch (IntegrationException $e) {
            return new VendResult(VendOutcome::NotSent, error: $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Stron vend crashed', ['reference' => $reference, 'exception' => $e::class]);

            return new VendResult(VendOutcome::Unknown, error: 'Unexpected error while vending.');
        }

        $status = $response->status();
        $data = $response->json();

        if ($status >= 500 || ! is_array($data)) {
            return new VendResult(VendOutcome::Unknown, httpStatus: $status, error: 'Vendor response was not readable (HTTP '.$status.').');
        }

        $row = array_is_list($data) ? ($data[0] ?? []) : $data;
        $raw = Redactor::redact(array_diff_key((array) $row, array_flip(['Token', 'Company_name', 'Username', 'Customer_phone'])));

        if ($status === 200 && is_array($row) && ! empty($row['Token'])) {
            return new VendResult(
                outcome: VendOutcome::Success,
                token: (string) $row['Token'],
                units: isset($row['Total_unit']) && is_numeric($row['Total_unit']) ? number_format((float) $row['Total_unit'], 2, '.', '') : null,
                unitLabel: $row['Unit'] ?? 'kWh',
                vendorReference: isset($row['Gen_time']) ? (string) $row['Gen_time'] : null,
                httpStatus: $status,
                raw: $raw,
            );
        }

        $message = is_array($row) ? ($row['Message'] ?? $row['message'] ?? $row['error'] ?? null) : null;

        return new VendResult(VendOutcome::Failed, httpStatus: $status, error: $message ? mb_substr((string) $message, 0, 200) : 'The vendor did not return a token.', raw: $raw);
    }

    private function classifyConnectionError(ConnectionException $e): VendOutcome
    {
        // cURL 6 = could not resolve host, 7 = could not connect: the request was never delivered.
        return preg_match('/cURL error (6|7)\b/', $e->getMessage()) ? VendOutcome::NotSent : VendOutcome::Unknown;
    }

    private function safeMessage(string $message): string
    {
        return preg_match('/cURL error (\d+)/', $message, $m) ? "Connection error (cURL {$m[1]})" : 'Connection error';
    }

    /**
     * @return array{CompanyName: string, UserName: string, PassWord: string}
     */
    private function credentials(): array
    {
        return [
            'CompanyName' => (string) config('services.stron.company'),
            'UserName' => (string) config('services.stron.username'),
            'PassWord' => (string) config('services.stron.password'),
        ];
    }

    private function client(int $timeout): PendingRequest
    {
        OutboundGuard::ensureLive('Stron');

        if (! $this->isConfigured()) {
            throw IntegrationException::notConfigured('Stron');
        }

        return Http::baseUrl(rtrim((string) config('services.stron.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout($timeout);
    }
}
