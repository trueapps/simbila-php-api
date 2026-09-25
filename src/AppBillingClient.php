<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi;

use InvalidArgumentException;
use TrueApps\SimbilaApi\Exception\SimbilaApiException;

/**
 * `/api/v1/app-billing*`: proforma/charge purchases against a reseller's own
 * "app users" (Lunchdrive's AI-credit top-ups, `appId = 'lunchdrive'`,
 * `appUser` = the Lunchdrive account id) - card-on-file or QR, plus the
 * one-off immediate charge of a stored card.
 *
 * Caching is deliberately not this class's job: a saved-card lookup is cheap
 * to call fresh, and an application that wants to cache it (as
 * `trueapps/lunchdrive-php` does, 10 minutes) does so in its own adapter.
 */
final class AppBillingClient
{
    public const PAYMENT_CARD = 'card';
    public const PAYMENT_QR = 'qr';

    /** `expires_in` bounds of a `payment: qr` purchase (seconds), Simbila default 3600. */
    public const QR_EXPIRES_IN_MIN = 60;
    public const QR_EXPIRES_IN_MAX = 3600;
    public const QR_EXPIRES_IN_DEFAULT = 3600;

    private Transport $transport;
    private string $appId;

    public function __construct(Transport $transport, string $appId)
    {
        $this->transport = $transport;
        $this->appId = $appId;
    }

    /**
     * Stored card of the app user (`GET /app-billing/card`).
     *
     * @return array{has_card: bool, card_last4: string|null, card_brand: string|null, authorized_at: string|null, last_charge_at: string|null, last_charge_status: string|null}
     * @throws SimbilaApiException `firm_not_found` is mapped to `has_card = false`, not thrown
     */
    public function card(string $appUser): array
    {
        $result = $this->transport->request('GET', 'app-billing/card', null, $this->identity($appUser));
        if ($result['status'] === 404 && ($result['body']['error_code'] ?? '') === 'firm_not_found') {
            return ['has_card' => false, 'card_last4' => null, 'card_brand' => null, 'authorized_at' => null, 'last_charge_at' => null, 'last_charge_status' => null];
        }
        $this->transport->assertOk($result, [200]);
        return $this->normaliseCard($result['body']);
    }

    /**
     * Proforma for a purchase (`POST /app-billing/purchase`). `payment: card`
     * (default) returns a GoPay link; `payment: qr` returns PayMe QR data
     * (`vs`, `qr_payload`, `qr_code`, `bank_account`, `expires_at`, `stream_url`,
     * `stream_token`) and no `gopay_url`; Simbila finalises the proforma into an
     * invoice once paid and e-mails it to `firm.fy_email`. For `qr`, `save_card`
     * and `return_url` are never sent (Simbila answers 400 `validation` for
     * `save_card`); `expiresIn` (60-3600s, default 3600) bounds the QR request.
     *
     * A `409 duplicate_external_ref` for the same pending purchase is treated
     * like a success (returns the existing `invoice_id`/QR or GoPay fields) -
     * this call is idempotent on `externalRef`.
     *
     * @param array<int, array{text: string, price_net?: float|int|string, price_gross?: float|int|string, vat: float|int|string, qty?: int|float|string}> $items
     *   exactly one of `price_net`/`price_gross` per line
     * @param array<string, mixed>|null $firm data to create the firm when it does not exist yet
     * @param self::PAYMENT_CARD|self::PAYMENT_QR $payment
     * @return array{invoice_id: int, invoice_number: string|null, status: string, external_ref: string, payment: string, gopay_url: string|null, gopay_payment_id: int|null, total: float, save_card: bool, public_url: string|null, invoice_pdf_url: string|null, vs: string|null, qr_payload: string|null, qr_code: string|null, bank_account: string|null, expires_at: string|null, stream_url: string|null, stream_token: string|null, stream_token_expires_at: string|null}
     * @throws SimbilaApiException
     */
    public function purchase(string $appUser, string $externalRef, array $items, bool $saveCard, ?string $returnUrl, string $lang, ?array $firm = null, string $payment = self::PAYMENT_CARD, ?int $expiresIn = null): array
    {
        if (!in_array($payment, [self::PAYMENT_CARD, self::PAYMENT_QR], true)) {
            throw new InvalidArgumentException('Unknown payment "' . $payment . '"');
        }
        $payload = $this->identity($appUser) + [
            'external_ref' => $externalRef,
            'currency' => 'CZK',
            'items' => self::normaliseItems($items),
            'payment' => $payment,
            'lang' => $lang,
        ];
        if ($payment === self::PAYMENT_QR) {
            $payload['expires_in'] = max(self::QR_EXPIRES_IN_MIN, min(self::QR_EXPIRES_IN_MAX, $expiresIn ?? self::QR_EXPIRES_IN_DEFAULT));
        } else {
            $payload['save_card'] = $saveCard;
            if ($returnUrl !== null && $returnUrl !== '') {
                $payload['return_url'] = $returnUrl;
            }
        }
        if ($firm !== null && $firm !== []) {
            $payload['firm'] = $firm;
        }
        $result = $this->transport->request('POST', 'app-billing/purchase', $payload);
        $body = $result['body'];

        if ($result['status'] === 409 && ($body['error_code'] ?? '') === 'duplicate_external_ref' && isset($body['invoice_id'])) {
            return $this->normalisePurchase($body, $externalRef);
        }
        $this->transport->assertOk($result, [200, 201]);
        return $this->normalisePurchase($body, $externalRef);
    }

    /**
     * State of a purchase/charge (`GET /app-billing/purchase/{external_ref}`).
     * `status` is always the app-billing status (pending|paid|expired|cancelled|failed);
     * a `payment: qr` purchase additionally carries `vs`, `expires_at` and
     * `payme_status` (pending|paid|expired|cancelled) of the underlying QR request.
     *
     * @return array{invoice_id: int, invoice_number: string|null, status: string, total: float, paid_total: float, paid_at: string|null, external_ref: string, app_charge: string, card_saved: bool, card_last4: string|null, card_brand: string|null, reconciliation_required: bool, gopay_url: string|null, invoice_pdf_url: string|null, payment: string, vs: string|null, expires_at: string|null, payme_status: string|null}
     * @throws SimbilaApiException `not_found` when Simbila knows no such purchase
     */
    public function purchaseStatus(string $externalRef): array
    {
        $result = $this->transport->request('GET', 'app-billing/purchase/' . rawurlencode($externalRef));
        $this->transport->assertOk($result, [200]);
        $body = $result['body'];
        return [
            'invoice_id' => (int) ($body['invoice_id'] ?? 0),
            'invoice_number' => self::optionalString($body['invoice_number'] ?? null),
            'status' => (string) ($body['status'] ?? ''),
            'total' => (float) ($body['total'] ?? 0),
            'paid_total' => (float) ($body['paid_total'] ?? 0),
            'paid_at' => self::optionalString($body['paid_at'] ?? null),
            'external_ref' => (string) ($body['external_ref'] ?? $externalRef),
            'app_charge' => (string) ($body['app_charge'] ?? 'purchase'),
            'card_saved' => (bool) ($body['card_saved'] ?? false),
            'card_last4' => self::optionalString($body['card_last4'] ?? null),
            'card_brand' => self::optionalString($body['card_brand'] ?? null),
            'reconciliation_required' => (bool) ($body['reconciliation_required'] ?? false),
            'gopay_url' => self::optionalString($body['gopay_url'] ?? null),
            'invoice_pdf_url' => self::optionalString($body['invoice_pdf_url'] ?? null),
            'payment' => (string) ($body['payment'] ?? self::PAYMENT_CARD),
            'vs' => self::optionalString($body['vs'] ?? null),
            'expires_at' => self::optionalString($body['expires_at'] ?? null),
            'payme_status' => self::optionalString($body['payme_status'] ?? null),
        ];
    }

    /**
     * Invoice + immediate charge of the stored card (`POST /app-billing/charge`).
     *
     * @param array<int, array{text: string, price_net?: float|int|string, price_gross?: float|int|string, vat: float|int|string, qty?: int|float|string}> $items
     * @return array{status: string, invoice_id: int, invoice_number: string|null, total: float, external_ref: string, gopay_payment_id: int|null, reconciliation_required: bool, gopay_error: string|null, invoice_pdf_url: string|null}
     * @throws SimbilaApiException `no_card`, `charge_failed` (402), `rate_limited` (429, retry_after), `busy`, ...
     */
    public function charge(string $appUser, string $externalRef, array $items, string $description, string $lang): array
    {
        $payload = $this->identity($appUser) + [
            'external_ref' => $externalRef,
            'currency' => 'CZK',
            'items' => self::normaliseItems($items),
            'description' => mb_substr($description, 0, 255),
            'lang' => $lang,
        ];
        $result = $this->transport->request('POST', 'app-billing/charge', $payload);
        $this->transport->assertOk($result, [200]);
        $body = $result['body'];
        return [
            'status' => (string) ($body['status'] ?? ''),
            'invoice_id' => (int) ($body['invoice_id'] ?? 0),
            'invoice_number' => self::optionalString($body['invoice_number'] ?? null),
            'total' => (float) ($body['total'] ?? 0),
            'external_ref' => (string) ($body['external_ref'] ?? $externalRef),
            'gopay_payment_id' => isset($body['gopay_payment_id']) ? (int) $body['gopay_payment_id'] : null,
            'reconciliation_required' => (bool) ($body['reconciliation_required'] ?? false),
            'gopay_error' => self::optionalString($body['gopay_error'] ?? null),
            'invoice_pdf_url' => self::optionalString($body['invoice_pdf_url'] ?? null),
        ];
    }

    /**
     * Removes the stored card (`DELETE /app-billing/card`); true when GoPay
     * voided the recurring mandate too (or there was nothing to remove).
     *
     * @throws SimbilaApiException
     */
    public function deleteCard(string $appUser): bool
    {
        $result = $this->transport->request('DELETE', 'app-billing/card', null, $this->identity($appUser));
        if ($result['status'] === 404 && ($result['body']['error_code'] ?? '') === 'firm_not_found') {
            return true;
        }
        $this->transport->assertOk($result, [200, 204]);
        return $result['status'] === 204 || (bool) ($result['body']['gopay_void'] ?? true);
    }

    /**
     * PDF of an issuer invoice (`GET /invoice/{id}/pdf`) - same endpoint
     * {@see InvoiceClient::pdf()} uses, exposed here too since an app-billing
     * invoice belongs to the reseller's own account, not a linked app user.
     *
     * @return array{content: string, filename: string}
     * @throws SimbilaApiException
     */
    public function invoicePdf(int $invoiceId): array
    {
        $result = $this->transport->request('GET', 'invoice/' . $invoiceId . '/pdf', null, [], true);
        $this->transport->assertOk($result, [200]);
        $filename = $invoiceId . '.pdf';
        $disposition = $result['headers']['Content-Disposition'][0] ?? '';
        if (preg_match('/filename="?([^";]+)"?/', $disposition, $m)) {
            $filename = basename(trim($m[1]));
        }
        return ['content' => $result['raw'], 'filename' => $filename];
    }

    /**
     * Items as Simbila expects them: the unit price rounded to 2 decimals,
     * integer `qty`, `vat` to 1 decimal. A line carries exactly one price key -
     * `price_gross` (incl. VAT, what the payer pays to the cent) or `price_net`
     * (without VAT); `price_gross` wins when both are given.
     *
     * @param array<int, array{text: string, price_net?: float|int|string, price_gross?: float|int|string, vat: float|int|string, qty?: int|float|string}> $items
     * @return array<int, array{text: string, price_net?: float, price_gross?: float, vat: float, qty: int}>
     */
    public static function normaliseItems(array $items): array
    {
        $normalised = [];
        foreach ($items as $item) {
            $row = ['text' => mb_substr((string) ($item['text'] ?? ''), 0, 255)];
            if (isset($item['price_gross'])) {
                $row['price_gross'] = round((float) $item['price_gross'], 2);
            } else {
                $row['price_net'] = round((float) ($item['price_net'] ?? 0), 2);
            }
            $row['vat'] = round((float) ($item['vat'] ?? 0), 1);
            $row['qty'] = max(1, (int) ($item['qty'] ?? 1));
            $normalised[] = $row;
        }
        return $normalised;
    }

    /**
     * @return array{app_id: string, app_user: string}
     */
    private function identity(string $appUser): array
    {
        return ['app_id' => $this->appId, 'app_user' => $appUser];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{has_card: bool, card_last4: string|null, card_brand: string|null, authorized_at: string|null, last_charge_at: string|null, last_charge_status: string|null}
     */
    private function normaliseCard(array $body): array
    {
        return [
            'has_card' => (bool) ($body['has_card'] ?? false),
            'card_last4' => self::optionalString($body['card_last4'] ?? null),
            'card_brand' => self::optionalString($body['card_brand'] ?? null),
            'authorized_at' => self::optionalString($body['authorized_at'] ?? null),
            'last_charge_at' => self::optionalString($body['last_charge_at'] ?? null),
            'last_charge_status' => self::optionalString($body['last_charge_status'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{invoice_id: int, invoice_number: string|null, status: string, external_ref: string, payment: string, gopay_url: string|null, gopay_payment_id: int|null, total: float, save_card: bool, public_url: string|null, invoice_pdf_url: string|null, vs: string|null, qr_payload: string|null, qr_code: string|null, bank_account: string|null, expires_at: string|null, stream_url: string|null, stream_token: string|null, stream_token_expires_at: string|null}
     */
    private function normalisePurchase(array $body, string $externalRef): array
    {
        return [
            'invoice_id' => (int) ($body['invoice_id'] ?? 0),
            'invoice_number' => self::optionalString($body['invoice_number'] ?? null),
            'status' => (string) ($body['status'] ?? 'pending'),
            'external_ref' => (string) ($body['external_ref'] ?? $externalRef),
            'payment' => (string) ($body['payment'] ?? self::PAYMENT_CARD),
            'gopay_url' => self::optionalString($body['gopay_url'] ?? null),
            'gopay_payment_id' => isset($body['gopay_payment_id']) ? (int) $body['gopay_payment_id'] : null,
            'total' => (float) ($body['total'] ?? 0),
            'save_card' => (bool) ($body['save_card'] ?? false),
            'public_url' => self::optionalString($body['public_url'] ?? null),
            'invoice_pdf_url' => self::optionalString($body['invoice_pdf_url'] ?? null),
            // PayMe fields of a `payment: qr` purchase (null for card)
            'vs' => self::optionalString($body['vs'] ?? null),
            'qr_payload' => self::optionalString($body['qr_payload'] ?? null),
            'qr_code' => self::optionalString($body['qr_code'] ?? null),
            'bank_account' => self::optionalString($body['bank_account'] ?? null),
            'expires_at' => self::optionalString($body['expires_at'] ?? null),
            'stream_url' => self::optionalString($body['stream_url'] ?? null),
            'stream_token' => self::optionalString($body['stream_token'] ?? null),
            'stream_token_expires_at' => self::optionalString($body['stream_token_expires_at'] ?? null),
        ];
    }

    /**
     * @param mixed $value
     */
    private static function optionalString($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
