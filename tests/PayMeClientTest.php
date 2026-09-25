<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TrueApps\SimbilaApi\Exception\SimbilaApiException;
use TrueApps\SimbilaApi\PayMeClient;
use TrueApps\SimbilaApi\Tests\Support\MocksHttp;
use TrueApps\SimbilaApi\Transport;

final class PayMeClientTest extends TestCase
{
    use MocksHttp;

    protected function setUp(): void
    {
        $this->setUpHttp();
    }

    private function client(): PayMeClient
    {
        return new PayMeClient(new Transport('sk_issuer', 'https://simbila.test', $this->mockHttpClient()));
    }

    public function testStatusMapsPaidTotalAndNotFound(): void
    {
        $this->mock->append($this->json(200, ['invoice_id' => 5001, 'status' => 'paid', 'amount' => 200.0, 'vs' => 'S1', 'paid_total' => 200.0, 'external_ref' => 'aicp-42-1', 'stream_url' => 'u', 'qr_code' => null, 'qr_payload' => null]));
        $this->mock->append($this->json(404, ['error' => 'Invoice not found']));
        $client = $this->client();

        $status = $client->status(5001);
        $this->assertSame('/api/v1/pay-me/5001', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('paid', $status['status']);
        $this->assertSame(200.0, $status['paid_total']);
        $this->assertNull($status['qr_code']);

        try {
            $client->status(1);
            $this->fail();
        } catch (SimbilaApiException $e) {
            $this->assertTrue($e->isNotFound());
        }
    }

    public function testCancelOutcomes(): void
    {
        $this->mock->append(new Response(204));
        $this->mock->append($this->json(409, ['error' => 'Cannot cancel an already paid invoice']));
        $this->mock->append($this->json(500, ['error' => 'Could not delete invoice 3']));
        $client = $this->client();

        $this->assertTrue($client->cancel(1));
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/pay-me/1', $this->lastRequest()->getUri()->getPath());
        $this->assertFalse($client->cancel(2));

        $this->expectException(SimbilaApiException::class);
        $client->cancel(3);
    }
}
