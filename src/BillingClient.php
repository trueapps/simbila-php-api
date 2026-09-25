<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi;

use TrueApps\SimbilaApi\Exception\SimbilaApiException;

/**
 * `/api/v1/billing*`: a reseller's subscription billing of one of its own
 * "app users" (Lunchdrive bills its Providers/paid Firms this way, `appId =
 * 'lunchdrive'`, `appUser` = the Lunchdrive account id). The Bearer key
 * identifies the reseller account; `appId`/`appUser` scope every call to one
 * subscription within it.
 */
final class BillingClient
{
    private Transport $transport;
    private string $appId;

    public function __construct(Transport $transport, string $appId)
    {
        $this->transport = $transport;
        $this->appId = $appId;
    }

    /**
     * The firm record linked to this app user (`GET /billing/client`).
     *
     * @return array<string, mixed>|null null when no firm is linked yet
     */
    public function client(string $appUser): ?array
    {
        $result = $this->send('GET', 'billing/client', null, $appUser);
        if ($result['status'] === 404) {
            return null;
        }
        $this->transport->assertOk($result, [200]);
        return $result['body'];
    }

    /**
     * Subscription status (`GET /billing/status`), or null when Simbila has no
     * status for this app user yet / the call otherwise 404s.
     *
     * @return array<string, mixed>|null
     */
    public function status(string $appUser): ?array
    {
        $result = $this->send('GET', 'billing/status', null, $appUser);
        if ($result['status'] === 404) {
            return null;
        }
        $this->transport->assertOk($result, [200]);
        return $result['body'];
    }

    /**
     * `POST /billing`: a business failure (missing required fields, ...) comes
     * back as `{status: 'error', result: '...'}` (HTTP 422), not an exception -
     * only a transport failure throws.
     *
     * @param array<string, mixed> $params `fy_*` firm data, `plan_*`, `r_lang`,
     *   `r_recurring_desc`, `useCC`, `items`
     * @return array<string, mixed> `status`, `result`, and `goPayLink` when `useCC` was requested
     */
    public function create(string $appUser, array $params): array
    {
        return $this->send('POST', 'billing', $params, $appUser)['body'];
    }

    /**
     * `PUT /billing`: same body contract as {@see create()}.
     *
     * @param array<string, mixed> $params `fy_*` fields
     * @return array<string, mixed> `status`, `result`
     */
    public function update(string $appUser, array $params): array
    {
        return $this->send('PUT', 'billing', $params, $appUser)['body'];
    }

    /**
     * `DELETE /billing`: always answers HTTP 200; true only when the body
     * confirms `status === 'ok'`.
     */
    public function delete(string $appUser): bool
    {
        $result = $this->send('DELETE', 'billing', null, $appUser);
        return ($result['body']['status'] ?? null) === 'ok';
    }

    /**
     * `GET /billing/invoices`: every invoice ever issued for this subscription.
     *
     * @return array<int, array<string, mixed>>
     */
    public function invoices(string $appUser): array
    {
        $result = $this->send('GET', 'billing/invoices', null, $appUser);
        $this->transport->assertOk($result, [200]);
        return array_is_list($result['body']) ? $result['body'] : ($result['body']['data'] ?? []);
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array{status: int, body: array<string, mixed>, raw: string, headers: array<string, string[]>}
     * @throws SimbilaApiException
     */
    private function send(string $method, string $path, ?array $json, string $appUser): array
    {
        return $this->transport->request($method, $path, $json, ['appId' => $this->appId, 'appUser' => $appUser]);
    }
}
