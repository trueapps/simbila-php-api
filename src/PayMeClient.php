<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi;

/**
 * `/api/v1/pay-me/{id}`: read/cancel of a QR-payment request. Creation goes
 * through {@see AppBillingClient::purchase()} with `payment: 'qr'` so Simbila
 * turns the paid proforma into an invoice - this client only reads and
 * cancels (`GET pay-me` is not exposed here since app-billing purchases never
 * need it; add it if a future consumer creates PayMe requests directly).
 */
final class PayMeClient
{
    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * `GET /pay-me/{id}`.
     *
     * @return array{invoice_id: int, status: string, amount: float, currency: string, vs: string, description: string, qr_payload: string|null, qr_code: string|null, bank_account: string|null, expires_at: string|null, external_ref: string|null, paid_total: float, stream_url: string|null, stream_token: string|null, stream_token_expires_at: string|null}
     * @throws \TrueApps\SimbilaApi\Exception\SimbilaApiException 404 for an unknown or non-PayMe invoice
     */
    public function status(int $invoiceId): array
    {
        $result = $this->transport->request('GET', 'pay-me/' . $invoiceId);
        $this->transport->assertOk($result, [200]);
        return $this->normalise($result['body']);
    }

    /**
     * `DELETE /pay-me/{id}`: false when the request was already paid/cancelled (409).
     */
    public function cancel(int $invoiceId): bool
    {
        $result = $this->transport->request('DELETE', 'pay-me/' . $invoiceId);
        if ($result['status'] === 409) {
            return false;
        }
        $this->transport->assertOk($result, [200, 204]);
        return true;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{invoice_id: int, status: string, amount: float, currency: string, vs: string, description: string, qr_payload: string|null, qr_code: string|null, bank_account: string|null, expires_at: string|null, external_ref: string|null, paid_total: float, stream_url: string|null, stream_token: string|null, stream_token_expires_at: string|null}
     */
    private function normalise(array $body): array
    {
        $string = static fn ($value): ?string => $value === null || $value === '' ? null : (string) $value;
        return [
            'invoice_id' => (int) ($body['invoice_id'] ?? 0),
            'status' => (string) ($body['status'] ?? ''),
            'amount' => (float) ($body['amount'] ?? 0),
            'currency' => (string) ($body['currency'] ?? 'CZK'),
            'vs' => (string) ($body['vs'] ?? ''),
            'description' => (string) ($body['description'] ?? ''),
            'qr_payload' => $string($body['qr_payload'] ?? null),
            'qr_code' => $string($body['qr_code'] ?? null),
            'bank_account' => $string($body['bank_account'] ?? null),
            'expires_at' => $string($body['expires_at'] ?? null),
            'external_ref' => $string($body['external_ref'] ?? null),
            'paid_total' => (float) ($body['paid_total'] ?? 0),
            'stream_url' => $string($body['stream_url'] ?? null),
            'stream_token' => $string($body['stream_token'] ?? null),
            'stream_token_expires_at' => $string($body['stream_token_expires_at'] ?? null),
        ];
    }
}
