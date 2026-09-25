<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi;

use TrueApps\SimbilaApi\Exception\SimbilaApiException;

/**
 * `/api/v1/invoice*`, `/api/v1/payment` and `/api/v1/whoami`: a Simbila
 * account's own invoicing of its own clients. The Bearer key alone identifies
 * the account - unlike {@see BillingClient} there is no `appId`/`appUser`.
 */
final class InvoiceClient
{
    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Verifies the key and returns the identity behind it (`GET /whoami`) - the
     * "check connection" call for a UI that lets a user paste in their own key.
     *
     * @return array{id: int, email: string, superuser: int, account_id: int|null, account: array<string, mixed>|null}
     * @throws SimbilaApiException on an invalid/expired key or transport failure
     */
    public function whoami(): array
    {
        $result = $this->transport->request('GET', 'whoami');
        $this->transport->assertOk($result, [200]);
        return $result['body'];
    }

    /**
     * `POST /invoice`.
     *
     * @param array<string, mixed> $params `i_*` fields (`i_type`, `i_lang`, `i_sent`,
     *   `i_firm_name`, `i_firm_ico`, `i_firm_dic`, `i_firm_street`, `i_firm_city`,
     *   `i_firm_zip`, `i_firm_email`, `i_auto_proinvoice`, `i_footer`, ...) and `items`
     * @return array<string, mixed> the created invoice, including `i_id`, `i_key`
     * @throws SimbilaApiException on validation failure (422) or transport failure
     */
    public function create(array $params): array
    {
        $result = $this->transport->request('POST', 'invoice', $params);
        $this->transport->assertOk($result, [200, 201]);
        return $result['body'];
    }

    /**
     * `GET /invoice/{id}`.
     *
     * @return array<string, mixed> includes `status`, `items`, `payments`
     */
    public function get(int $id): array
    {
        $result = $this->transport->request('GET', 'invoice/' . $id);
        $this->transport->assertOk($result, [200]);
        return $result['body'];
    }

    /**
     * `DELETE /invoice/{id}`.
     */
    public function delete(int $id): bool
    {
        $result = $this->transport->request('DELETE', 'invoice/' . $id);
        if ($result['status'] === 404) {
            return false;
        }
        $this->transport->assertOk($result, [200, 204]);
        return true;
    }

    /**
     * `POST /invoice/{id}/send`: has Simbila e-mail the invoice to its firm.
     */
    public function send(int $id): bool
    {
        $result = $this->transport->request('POST', 'invoice/' . $id . '/send');
        $this->transport->assertOk($result, [200]);
        return true;
    }

    /**
     * Raw bytes of the invoice PDF (`GET /invoice/{id}/pdf`) - Bearer-authenticated
     * like every other `/api/v1` call, so a caller that wants to hand this to a
     * browser must proxy it (see the `trueapps/lunchdrive-php` adapter).
     */
    public function pdf(int $id): string
    {
        $result = $this->transport->request('GET', 'invoice/' . $id . '/pdf', null, [], true);
        if ($result['status'] !== 200) {
            throw new SimbilaApiException($result['status'], 'http_' . $result['status'], 'Simbila returned HTTP ' . $result['status'] . ' for invoice PDF ' . $id, $result['body']);
        }
        return $result['raw'];
    }

    /**
     * Records a payment against an invoice (`POST /payment`) - e.g. one already
     * collected by the caller through its own payment gateway.
     *
     * @param array<string, mixed> $params `p_*` fields (`p_i_id`, `p_amount`, `p_currency`,
     *   `p_payment_type`, `p_note`, ...)
     * @return array<string, mixed>
     */
    public function createPayment(array $params): array
    {
        $result = $this->transport->request('POST', 'payment', $params);
        $this->transport->assertOk($result, [200, 201]);
        return $result['body'];
    }
}
