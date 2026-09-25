<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TrueApps\SimbilaApi\Exception\SimbilaApiException;
use TrueApps\SimbilaApi\InvoiceClient;
use TrueApps\SimbilaApi\Tests\Support\MocksHttp;
use TrueApps\SimbilaApi\Transport;

final class InvoiceClientTest extends TestCase
{
    use MocksHttp;

    protected function setUp(): void
    {
        $this->setUpHttp();
    }

    private function client(): InvoiceClient
    {
        return new InvoiceClient(new Transport('sk_provider', 'https://simbila.test', $this->mockHttpClient()));
    }

    public function testWhoamiSendsBearerAndReturnsIdentity(): void
    {
        $this->mock->append($this->json(200, ['id' => 7, 'email' => 'provider@example.com', 'account_id' => 3, 'account' => ['ia_id' => 3, 'ia_name' => 'Acme s.r.o.']]));

        $identity = $this->client()->whoami();

        $request = $this->lastRequest();
        $this->assertSame('/api/v1/whoami', $request->getUri()->getPath());
        $this->assertSame('Bearer sk_provider', $request->getHeaderLine('Authorization'));
        $this->assertSame('Acme s.r.o.', $identity['account']['ia_name']);
    }

    public function testWhoamiThrowsOnAnInvalidKey(): void
    {
        $this->mock->append($this->json(401, ['error' => 'Invalid API key']));
        try {
            $this->client()->whoami();
            $this->fail();
        } catch (SimbilaApiException $e) {
            $this->assertTrue($e->isUnauthorized());
        }
    }

    public function testCreateReturnsTheCreatedInvoice(): void
    {
        $this->mock->append($this->json(201, ['i_id' => 501, 'i_key' => 'abc123', 'i_number' => '2026001']));

        $invoice = $this->client()->create(['i_type' => 'invoice', 'i_firm_name' => 'Client s.r.o.']);

        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/invoice', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(501, $invoice['i_id']);
    }

    public function testCreateThrowsOnValidationFailure(): void
    {
        $this->mock->append($this->json(422, ['message' => 'Validation failed']));
        $this->expectException(SimbilaApiException::class);
        $this->client()->create([]);
    }

    public function testGetReturnsStatusAndPayments(): void
    {
        $this->mock->append($this->json(200, ['i_id' => 501, 'status' => 'paid', 'payments' => [['p_amount' => 100, 'p_note' => 'x']]]));
        $invoice = $this->client()->get(501);
        $this->assertSame('paid', $invoice['status']);
        $this->assertSame('/api/v1/invoice/501', $this->lastRequest()->getUri()->getPath());
    }

    public function testDeleteOutcomes(): void
    {
        $this->mock->append(new Response(204));
        $this->mock->append($this->json(404, ['error' => 'not found']));

        $this->assertTrue($this->client()->delete(1));
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertFalse($this->client()->delete(2));
    }

    public function testSend(): void
    {
        $this->mock->append($this->json(200, []));
        $this->assertTrue($this->client()->send(501));
        $this->assertSame('/api/v1/invoice/501/send', $this->lastRequest()->getUri()->getPath());
    }

    public function testCreatePaymentSendsTheGivenFields(): void
    {
        $this->mock->append($this->json(201, ['p_id' => 9001, 'p_amount' => 150.0]));

        $payment = $this->client()->createPayment([
            'p_i_id' => 501,
            'p_amount' => 150.0,
            'p_currency' => 'CZK',
            'p_note' => 'Stripe payment pi_123',
        ]);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        $this->assertSame(501, $body['p_i_id']);
        $this->assertSame(9001, $payment['p_id']);
    }

    public function testPdfReturnsRawBytes(): void
    {
        $this->mock->append(new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4 fake bytes'));
        $bytes = $this->client()->pdf(501);
        $this->assertSame('%PDF-1.4 fake bytes', $bytes);
        $this->assertSame('/api/v1/invoice/501/pdf', $this->lastRequest()->getUri()->getPath());
    }

    public function testPdfThrowsOnFailure(): void
    {
        $this->mock->append(new Response(404, [], ''));
        $this->expectException(SimbilaApiException::class);
        $this->client()->pdf(999);
    }
}
