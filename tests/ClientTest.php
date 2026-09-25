<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi\Tests;

use PHPUnit\Framework\TestCase;
use TrueApps\SimbilaApi\AppBillingClient;
use TrueApps\SimbilaApi\BillingClient;
use TrueApps\SimbilaApi\Client;
use TrueApps\SimbilaApi\Exception\SimbilaApiException;
use TrueApps\SimbilaApi\InvoiceClient;
use TrueApps\SimbilaApi\PayMeClient;
use TrueApps\SimbilaApi\Tests\Support\MocksHttp;

final class ClientTest extends TestCase
{
    use MocksHttp;

    protected function setUp(): void
    {
        $this->setUpHttp();
    }

    public function testRejectsAnEmptyApiKey(): void
    {
        $this->expectException(SimbilaApiException::class);
        new Client('  ');
    }

    public function testResourceClientsAreLazyAndMemoised(): void
    {
        $client = new Client('sk_test', ['appId' => 'lunchdrive', 'httpClient' => $this->mockHttpClient()]);

        $this->assertInstanceOf(InvoiceClient::class, $client->invoice());
        $this->assertSame($client->invoice(), $client->invoice(), 'same instance on repeated access');
        $this->assertInstanceOf(PayMeClient::class, $client->payMe());
        $this->assertInstanceOf(BillingClient::class, $client->billing());
        $this->assertInstanceOf(AppBillingClient::class, $client->appBilling());
    }

    public function testBillingAndAppBillingRequireAnAppId(): void
    {
        $client = new Client('sk_test', ['httpClient' => $this->mockHttpClient()]);

        try {
            $client->billing();
            $this->fail('expected not_configured');
        } catch (SimbilaApiException $e) {
            $this->assertSame(SimbilaApiException::CODE_NOT_CONFIGURED, $e->getErrorCode());
        }
        try {
            $client->appBilling();
            $this->fail('expected not_configured');
        } catch (SimbilaApiException $e) {
            $this->assertSame(SimbilaApiException::CODE_NOT_CONFIGURED, $e->getErrorCode());
        }
        // invoice()/payMe()/whoami() need no appId
        $this->assertInstanceOf(InvoiceClient::class, $client->invoice());
        $this->assertInstanceOf(PayMeClient::class, $client->payMe());
    }

    public function testWhoamiDelegatesToInvoiceClient(): void
    {
        $this->mock->append($this->json(200, ['id' => 1, 'email' => 'a@example.test', 'account_id' => 3, 'account' => ['ia_name' => 'Acme']]));
        $client = new Client('sk_test', ['httpClient' => $this->mockHttpClient()]);

        $identity = $client->whoami();

        $this->assertSame('/api/v1/whoami', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('Acme', $identity['account']['ia_name']);
    }

    public function testBaseUrlOptionIsPassedThrough(): void
    {
        $this->mock->append($this->json(200, ['ok' => true]));
        $client = new Client('sk_test', ['baseUrl' => 'https://dev.simbila.test/api', 'httpClient' => $this->mockHttpClient()]);

        $client->whoami();

        $this->assertSame('dev.simbila.test', $this->lastRequest()->getUri()->getHost());
        $this->assertSame('/api/v1/whoami', $this->lastRequest()->getUri()->getPath());
    }
}
