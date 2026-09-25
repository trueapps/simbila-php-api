<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi;

use GuzzleHttp\ClientInterface;
use TrueApps\SimbilaApi\Exception\SimbilaApiException;

/**
 * Entry point of the Simbila `/api/v1` PHP client.
 *
 * ```php
 * $client = new Client('sk_live_...', ['appId' => 'lunchdrive']);
 * $client->whoami();
 * $client->billing()->status('345');
 * $client->invoice()->create([...]);
 * $client->appBilling()->purchase('345', 'aicp-345-1', $items, false, null, 'cs');
 * $client->payMe()->status(5001);
 * ```
 *
 * Every resource client shares one {@see Transport} (one Bearer key, one base
 * URL, one HTTP client) and is built lazily on first access.
 */
final class Client
{
    private Transport $transport;
    private string $appId;

    private ?AppBillingClient $appBilling = null;
    private ?PayMeClient $payMe = null;
    private ?BillingClient $billing = null;
    private ?InvoiceClient $invoice = null;

    /**
     * @param string $apiKey Bearer API key from the Simbila account settings
     * @param array{baseUrl?: string, appId?: string, timeout?: float, httpClient?: ClientInterface} $options
     *   `baseUrl` defaults to `https://simbila.com` (a trailing `/api` or `/api/v1` is
     *   stripped, so passing the old `simbilaUrl` value works too); `appId` is only
     *   needed for {@see billing()}/{@see appBilling()}; `httpClient` injects a PSR-18/Guzzle
     *   client (for tests, or a shared connection pool) instead of a default one.
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if (trim($apiKey) === '') {
            throw new SimbilaApiException(0, SimbilaApiException::CODE_NOT_CONFIGURED, 'Simbila API key is required');
        }
        $this->transport = new Transport(
            $apiKey,
            $options['baseUrl'] ?? 'https://simbila.com',
            $options['httpClient'] ?? null,
            $options['timeout'] ?? Transport::DEFAULT_TIMEOUT,
        );
        $this->appId = $options['appId'] ?? '';
    }

    /**
     * Identity behind the API key (`GET /whoami`) - who it belongs to and, for
     * an external integration's key, which account it is scoped to.
     *
     * @return array{id: int, email: string, superuser: int, account_id: int|null, account: array<string, mixed>|null}
     */
    public function whoami(): array
    {
        return $this->invoice()->whoami();
    }

    public function appBilling(): AppBillingClient
    {
        return $this->appBilling ??= new AppBillingClient($this->transport, $this->requireAppId());
    }

    public function payMe(): PayMeClient
    {
        return $this->payMe ??= new PayMeClient($this->transport);
    }

    public function billing(): BillingClient
    {
        return $this->billing ??= new BillingClient($this->transport, $this->requireAppId());
    }

    public function invoice(): InvoiceClient
    {
        return $this->invoice ??= new InvoiceClient($this->transport);
    }

    private function requireAppId(): string
    {
        if ($this->appId === '') {
            throw new SimbilaApiException(0, SimbilaApiException::CODE_NOT_CONFIGURED, 'The "appId" option is required for billing()/appBilling()');
        }
        return $this->appId;
    }
}
