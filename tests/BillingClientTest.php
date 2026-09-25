<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi\Tests;

use PHPUnit\Framework\TestCase;
use TrueApps\SimbilaApi\BillingClient;
use TrueApps\SimbilaApi\Exception\SimbilaApiException;
use TrueApps\SimbilaApi\Tests\Support\MocksHttp;
use TrueApps\SimbilaApi\Transport;

final class BillingClientTest extends TestCase
{
    use MocksHttp;

    protected function setUp(): void
    {
        $this->setUpHttp();
    }

    private function client(): BillingClient
    {
        return new BillingClient(new Transport('sk_issuer', 'https://simbila.test', $this->mockHttpClient()), 'lunchdrive');
    }

    public function testStatusSendsAppScopingAndReturnsTheBody(): void
    {
        $this->mock->append($this->json(200, ['status' => 'ok', 'valid_to' => '2026-12-31']));

        $status = $this->client()->status('42');

        $request = $this->lastRequest();
        $this->assertSame('/api/v1/billing/status', $request->getUri()->getPath());
        $this->assertSame('appId=lunchdrive&appUser=42', $request->getUri()->getQuery());
        $this->assertSame('ok', $status['status']);
    }

    public function testStatusReturnsNullOn404(): void
    {
        $this->mock->append($this->json(404, ['error' => 'No subscription found']));
        $this->assertNull($this->client()->status('1'));
    }

    public function testClientReturnsNullOn404(): void
    {
        $this->mock->append($this->json(404, ['error' => 'not linked']));
        $this->assertNull($this->client()->client('1'));
    }

    public function testClientReturnsTheFirm(): void
    {
        $this->mock->append($this->json(200, ['fy_name' => 'Acme']));
        $firm = $this->client()->client('1');
        $this->assertSame('Acme', $firm['fy_name']);
        $this->assertSame('/api/v1/billing/client', $this->lastRequest()->getUri()->getPath());
    }

    public function testCreateReturnsTheBodyEvenOnA422BusinessFailure(): void
    {
        $this->mock->append($this->json(422, ['status' => 'error', 'result' => 'Some of plan_text, plan_price, plan_vat is not set.']));
        $result = $this->client()->create('1', ['fy_name' => 'Acme']);
        $this->assertSame('error', $result['status']);

        $this->mock->append($this->json(201, ['status' => 'ok', 'goPayLink' => 'https://pay.example/x']));
        $result = $this->client()->create('1', ['fy_name' => 'Acme', 'useCC' => true]);
        $this->assertSame('ok', $result['status']);
        $this->assertSame('https://pay.example/x', $result['goPayLink']);
    }

    public function testUpdateReturnsTheBody(): void
    {
        $this->mock->append($this->json(200, ['status' => 'ok', 'result' => 'Billing lunchdrive:1 updated']));
        $result = $this->client()->update('1', ['fy_name' => 'Acme s.r.o.']);
        $this->assertSame('PUT', $this->lastRequest()->getMethod());
        $this->assertSame('ok', $result['status']);
    }

    public function testDeleteIsTrueOnlyWhenTheBodyStatusIsOk(): void
    {
        $this->mock->append($this->json(200, ['status' => 'ok']));
        $this->mock->append($this->json(200, ['status' => 'error']));
        $client = $this->client();

        $this->assertTrue($client->delete('1'));
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertFalse($client->delete('1'));
    }

    public function testInvoicesAcceptsAPlainListOrADataWrapper(): void
    {
        $this->mock->append($this->json(200, [['i_id' => 1], ['i_id' => 2]]));
        $this->assertCount(2, $this->client()->invoices('1'));

        $this->mock->append($this->json(200, ['data' => [['i_id' => 3]]]));
        $this->assertCount(1, $this->client()->invoices('1'));
    }

    public function testInvoicesThrowsOnFailure(): void
    {
        $this->mock->append($this->json(500, []));
        $this->expectException(SimbilaApiException::class);
        $this->client()->invoices('1');
    }
}
