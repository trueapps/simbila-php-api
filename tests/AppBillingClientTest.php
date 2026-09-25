<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TrueApps\SimbilaApi\AppBillingClient;
use TrueApps\SimbilaApi\Exception\SimbilaApiException;
use TrueApps\SimbilaApi\Tests\Support\MocksHttp;
use TrueApps\SimbilaApi\Transport;

/**
 * Request/response mapping of the app-billing client against the documented
 * contract (simbila-app-billing-api.md) - the same scenarios as the old
 * SimbilaAppBillingClientTest in trueapps/lunchdrive-php, minus the card
 * cache (an application concern, not this package's).
 */
final class AppBillingClientTest extends TestCase
{
    use MocksHttp;

    private const KEY = 'sk_issuer_secret_key_123';

    protected function setUp(): void
    {
        $this->setUpHttp();
    }

    private function lastBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true);
    }

    private function client(): AppBillingClient
    {
        return new AppBillingClient(new Transport(self::KEY, 'https://simbila.test', $this->mockHttpClient()), 'lunchdrive');
    }

    public function testCardSendsIdentityAndBearerAuth(): void
    {
        $this->mock->append($this->json(200, ['has_card' => true, 'card_last4' => '1234', 'card_brand' => 'VISA', 'authorized_at' => '2026-09-06T10:12:00+02:00']));

        $card = $this->client()->card('4821');

        $this->assertTrue($card['has_card']);
        $this->assertSame('1234', $card['card_last4']);
        $this->assertSame('VISA', $card['card_brand']);
        $request = $this->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/app-billing/card', $request->getUri()->getPath());
        $this->assertSame('app_id=lunchdrive&app_user=4821', $request->getUri()->getQuery());
        $this->assertSame('Bearer ' . self::KEY, $request->getHeaderLine('Authorization'));
    }

    public function testUnknownFirmMeansNoCard(): void
    {
        $this->mock->append($this->json(404, ['error' => 'Firm not found', 'error_code' => 'firm_not_found']));
        $card = $this->client()->card('1');
        $this->assertFalse($card['has_card']);
        $this->assertNull($card['card_last4']);
    }

    public function testPurchaseSendsNormalisedItemsAndMapsTheResponse(): void
    {
        $this->mock->append($this->json(201, [
            'invoice_id' => 88213, 'invoice_number' => '9-2026-0412', 'vs' => '920260412', 'total' => 200.0, 'currency' => 'CZK',
            'status' => 'pending', 'external_ref' => 'aicp-4821-917', 'gopay_url' => 'https://gw.gopay.com/gw/v3/x', 'gopay_payment_id' => 3123456789,
            'save_card' => true, 'public_url' => 'https://simbila.test/invoice/public/k', 'invoice_pdf_url' => '/api/v1/invoice/88213/pdf',
        ]));

        $result = $this->client()->purchase(
            '4821',
            'aicp-4821-917',
            [['text' => 'AI kredit 200 Kč', 'price_net' => 165.289, 'vat' => 21, 'qty' => '1']],
            true,
            'https://www.lunchdrive.cz/cs/ai/credit/return?ref=aicp-4821-917',
            'cs',
            ['fy_name' => 'Firma', 'fy_ico' => '123']
        );

        $body = $this->lastBody();
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/app-billing/purchase', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('lunchdrive', $body['app_id']);
        $this->assertSame('4821', $body['app_user']);
        $this->assertSame('CZK', $body['currency']);
        $this->assertEquals([['text' => 'AI kredit 200 Kč', 'price_net' => 165.29, 'vat' => 21, 'qty' => 1]], $body['items']);
        $this->assertTrue($body['save_card']);
        $this->assertSame('https://www.lunchdrive.cz/cs/ai/credit/return?ref=aicp-4821-917', $body['return_url']);
        $this->assertSame('cs', $body['lang']);
        $this->assertSame(['fy_name' => 'Firma', 'fy_ico' => '123'], $body['firm']);

        $this->assertSame(88213, $result['invoice_id']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('https://gw.gopay.com/gw/v3/x', $result['gopay_url']);
        $this->assertSame(3123456789, $result['gopay_payment_id']);
        $this->assertTrue($result['save_card']);
        $this->assertSame('/api/v1/invoice/88213/pdf', $result['invoice_pdf_url']);
    }

    public function testPurchaseWithoutReturnUrlAndFirmOmitsThem(): void
    {
        $this->mock->append($this->json(201, ['invoice_id' => 1, 'status' => 'pending', 'external_ref' => 'r', 'gopay_url' => 'u']));
        $this->client()->purchase('1', 'r', [['text' => 'x', 'price_net' => 1, 'vat' => 0]], false, null, 'en');
        $body = $this->lastBody();
        $this->assertArrayNotHasKey('return_url', $body);
        $this->assertArrayNotHasKey('firm', $body);
        $this->assertFalse($body['save_card']);
        $this->assertSame('card', $body['payment'], 'card is the default payment');
        $this->assertArrayNotHasKey('expires_in', $body);
    }

    public function testQrPurchaseSendsPaymentQrAndMapsThePayMeFields(): void
    {
        $this->mock->append($this->json(201, [
            'invoice_id' => 88300, 'invoice_number' => '9-2026-0500', 'status' => 'pending', 'total' => 200.0, 'currency' => 'CZK',
            'external_ref' => 'aicp-4821-918', 'app_charge' => 'purchase', 'payment' => 'qr',
            'vs' => '920260500', 'qr_payload' => 'SPD*1.0*ACC:CZ12*AM:200.00*X-VS:920260500', 'qr_code' => 'data:image/png;base64,QUJD', 'bank_account' => '123/0100',
            'expires_at' => '2026-09-07T12:00:00+02:00', 'stream_url' => 'https://simbila.test/api/v1/pay-me/88300/stream', 'stream_token' => 'pmst_abc', 'stream_token_expires_at' => '2026-09-07T12:00:00+02:00',
        ]));

        $result = $this->client()->purchase(
            '4821',
            'aicp-4821-918',
            [['text' => 'AI kredit 200 Kč', 'price_net' => 165.29, 'vat' => 21]],
            false,
            'https://www.lunchdrive.cz/cs/ai/credit/return?ref=aicp-4821-918',
            'cs',
            ['fy_name' => 'Firma', 'fy_email' => 'invoices@example.test'],
            'qr',
            7200
        );

        $body = $this->lastBody();
        $this->assertSame('qr', $body['payment']);
        $this->assertSame(3600, $body['expires_in'], 'clamped to the maximum');
        $this->assertArrayNotHasKey('save_card', $body, 'Simbila answers 400 validation for save_card with qr');
        $this->assertArrayNotHasKey('return_url', $body, 'no gateway return for a QR request');
        $this->assertSame(['fy_name' => 'Firma', 'fy_email' => 'invoices@example.test'], $body['firm']);

        $this->assertSame(88300, $result['invoice_id']);
        $this->assertSame('qr', $result['payment']);
        $this->assertNull($result['gopay_url']);
        $this->assertSame('920260500', $result['vs']);
        $this->assertSame('SPD*1.0*ACC:CZ12*AM:200.00*X-VS:920260500', $result['qr_payload']);
        $this->assertSame('data:image/png;base64,QUJD', $result['qr_code']);
        $this->assertSame('123/0100', $result['bank_account']);
        $this->assertSame('https://simbila.test/api/v1/pay-me/88300/stream', $result['stream_url']);
        $this->assertSame('pmst_abc', $result['stream_token']);
    }

    public function testQrPurchaseExpiresInDefaultAndMinimum(): void
    {
        foreach ([[null, 3600], [10, 60], [600, 600]] as [$given, $sent]) {
            $this->mock->append($this->json(201, ['invoice_id' => 1, 'status' => 'pending', 'external_ref' => 'r', 'payment' => 'qr', 'vs' => '1']));
            $this->client()->purchase('1', 'r', [['text' => 'x', 'price_net' => 1, 'vat' => 0]], false, null, 'en', null, 'qr', $given);
            $this->assertSame($sent, $this->lastBody()['expires_in'], var_export($given, true));
        }
        $this->expectException(InvalidArgumentException::class);
        $this->client()->purchase('1', 'r', [['text' => 'x', 'price_net' => 1, 'vat' => 0]], false, null, 'en', null, 'cash');
    }

    public function testDuplicatePendingQrPurchaseIsReturnedWithItsQrFields(): void
    {
        $this->mock->append($this->json(409, [
            'error' => 'A pending purchase with this external_ref already exists', 'error_code' => 'duplicate_external_ref',
            'invoice_id' => 88300, 'status' => 'pending', 'external_ref' => 'aicp-4821-918', 'payment' => 'qr', 'total' => 200.0,
            'vs' => '920260500', 'qr_code' => 'data:image/png;base64,QUJD', 'stream_token' => 'pmst_again',
        ]));

        $result = $this->client()->purchase('4821', 'aicp-4821-918', [['text' => 'x', 'price_net' => 1, 'vat' => 21]], false, null, 'cs', null, 'qr');

        $this->assertSame(88300, $result['invoice_id']);
        $this->assertSame('920260500', $result['vs']);
        $this->assertSame('data:image/png;base64,QUJD', $result['qr_code']);
        $this->assertNull($result['gopay_url']);
    }

    public function testDuplicatePendingPurchaseIsReturnedLikeASuccess(): void
    {
        $this->mock->append($this->json(409, [
            'error' => 'A pending purchase with this external_ref already exists', 'error_code' => 'duplicate_external_ref',
            'invoice_id' => 88213, 'status' => 'pending', 'external_ref' => 'aicp-4821-917', 'gopay_url' => 'https://gw.gopay.com/existing', 'gopay_payment_id' => 5,
        ]));

        $result = $this->client()->purchase('4821', 'aicp-4821-917', [['text' => 'x', 'price_net' => 1, 'vat' => 21]], false, null, 'cs');

        $this->assertSame(88213, $result['invoice_id']);
        $this->assertSame('https://gw.gopay.com/existing', $result['gopay_url']);
    }

    /**
     * @return array<string, array{int, string, array<string, mixed>}>
     */
    public static function purchaseErrors(): array
    {
        return [
            'already_paid' => [409, 'already_paid', ['invoice_id' => 1]],
            'invalid_items' => [400, 'invalid_items', []],
            'amount_out_of_range' => [400, 'amount_out_of_range', ['total' => 5, 'min' => 10, 'max' => 50000]],
            'invalid_firm' => [400, 'invalid_firm', ['details' => ['fy_name' => 'required']]],
            'firm_not_found' => [404, 'firm_not_found', []],
            'gopay_not_configured' => [503, 'gopay_not_configured', []],
            'busy' => [503, 'busy', []],
        ];
    }

    /**
     * @dataProvider purchaseErrors
     * @param array<string, mixed> $extra
     */
    public function testPurchaseErrorsBecomeSimbilaApiExceptions(int $status, string $code, array $extra): void
    {
        $this->mock->append($this->json($status, ['error' => 'msg ' . $code, 'error_code' => $code] + $extra));
        try {
            $this->client()->purchase('1', 'r', [['text' => 'x', 'price_net' => 1, 'vat' => 21]], false, null, 'cs');
            $this->fail('expected ' . $code);
        } catch (SimbilaApiException $e) {
            $this->assertSame($status, $e->getStatus());
            $this->assertSame($code, $e->getErrorCode());
            $this->assertSame('msg ' . $code, $e->getMessage());
            foreach ($extra as $key => $value) {
                $this->assertSame($value, $e->extra($key));
            }
            $this->assertSame($status >= 500, $e->isTransient());
        }
    }

    public function testPurchaseStatusMapsTheContract(): void
    {
        $this->mock->append($this->json(200, [
            'invoice_id' => 88213, 'invoice_number' => '9-2026-0412', 'status' => 'paid', 'total' => 200.0, 'paid_total' => 200.0, 'paid_at' => '2026-09-06T11:00:00+02:00',
            'external_ref' => 'aicp-4821-917', 'app_charge' => 'purchase', 'save_card' => true, 'card_saved' => true, 'card_last4' => '1234', 'card_brand' => 'VISA',
            'gopay_payment_id' => 1, 'gopay_url' => null, 'reconciliation_required' => false, 'invoice_pdf_url' => '/api/v1/invoice/88213/pdf',
        ]));

        $status = $this->client()->purchaseStatus('aicp-4821-917');

        $this->assertSame('/api/v1/app-billing/purchase/aicp-4821-917', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('paid', $status['status']);
        $this->assertSame(200.0, $status['paid_total']);
        $this->assertTrue($status['card_saved']);
        $this->assertSame('1234', $status['card_last4']);
        $this->assertFalse($status['reconciliation_required']);
        $this->assertSame('card', $status['payment'], 'card when Simbila sends no payment field');
        $this->assertNull($status['payme_status']);
    }

    public function testPurchaseStatusNotFound(): void
    {
        $this->mock->append($this->json(404, ['error' => 'No purchase with this external_ref', 'error_code' => 'not_found']));
        try {
            $this->client()->purchaseStatus('nope');
            $this->fail();
        } catch (SimbilaApiException $e) {
            $this->assertSame(404, $e->getStatus());
            $this->assertSame('not_found', $e->getErrorCode());
        }
    }

    public function testChargePaidAndPendingWithReconciliation(): void
    {
        $this->mock->append($this->json(200, ['status' => 'paid', 'invoice_id' => 88290, 'invoice_number' => 'n', 'total' => 200.0, 'gopay_payment_id' => 7, 'external_ref' => 'aicp-4821-918', 'invoice_pdf_url' => '/api/v1/invoice/88290/pdf']));
        $this->mock->append($this->json(200, ['status' => 'pending', 'invoice_id' => 88291, 'external_ref' => 'aicp-4821-919', 'reconciliation_required' => true, 'gopay_error' => 'GOPAY_UNAVAILABLE']));
        $client = $this->client();

        $paid = $client->charge('4821', 'aicp-4821-918', [['text' => 'AI kredit', 'price_net' => 165.29, 'vat' => 21]], 'AI kredit', 'cs');
        $body = $this->lastBody();
        $this->assertSame('/api/v1/app-billing/charge', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('4821', $body['app_user']);
        $this->assertArrayNotHasKey('save_card', $body);
        $this->assertSame('paid', $paid['status']);
        $this->assertSame(88290, $paid['invoice_id']);
        $this->assertFalse($paid['reconciliation_required']);

        $pending = $client->charge('4821', 'aicp-4821-919', [['text' => 'x', 'price_net' => 1, 'vat' => 21]], 'x', 'cs');
        $this->assertSame('pending', $pending['status']);
        $this->assertTrue($pending['reconciliation_required']);
        $this->assertSame('GOPAY_UNAVAILABLE', $pending['gopay_error']);
    }

    /**
     * @return array<string, array{int, string, array<string, mixed>}>
     */
    public static function chargeErrors(): array
    {
        return [
            'charge_failed' => [402, 'charge_failed', ['gopay_error' => 'PAYMENT_DECLINED', 'card_invalidated' => false]],
            'charge_failed invalidated' => [402, 'charge_failed', ['gopay_error' => 'PAYMENT_RECURRENCE_FAILED', 'card_invalidated' => true]],
            'no_card' => [409, 'no_card', []],
            'rate_limited' => [429, 'rate_limited', ['retry_after' => 42]],
            'busy' => [503, 'busy', []],
            'firm_not_found' => [404, 'firm_not_found', []],
        ];
    }

    /**
     * @dataProvider chargeErrors
     * @param array<string, mixed> $extra
     */
    public function testChargeErrorsCarryTheirDetails(int $status, string $code, array $extra): void
    {
        $this->mock->append($this->json($status, ['error' => 'e', 'error_code' => $code] + $extra));
        try {
            $this->client()->charge('1', 'r', [['text' => 'x', 'price_net' => 1, 'vat' => 21]], 'x', 'cs');
            $this->fail('expected ' . $code);
        } catch (SimbilaApiException $e) {
            $this->assertSame($status, $e->getStatus());
            $this->assertSame($code, $e->getErrorCode());
            $this->assertSame((bool) ($extra['card_invalidated'] ?? false), (bool) $e->extra('card_invalidated', false));
            $this->assertSame($extra['retry_after'] ?? null, $e->getRetryAfter());
            $this->assertSame($status === 429 || $status >= 500, $e->isTransient());
        }
    }

    public function testDeleteCardOutcomes(): void
    {
        $this->mock->append(new Response(204));
        $this->mock->append($this->json(200, ['gopay_void' => false]));
        $this->mock->append($this->json(404, ['error' => 'x', 'error_code' => 'firm_not_found']));
        $this->mock->append($this->json(401, ['error' => 'Unauthorized']));
        $client = $this->client();

        $this->assertTrue($client->deleteCard('1'));
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame('app_id=lunchdrive&app_user=1', $this->lastRequest()->getUri()->getQuery());
        $this->assertFalse($client->deleteCard('1'), 'GoPay refused the void');
        $this->assertTrue($client->deleteCard('1'), 'no firm = nothing to delete');

        try {
            $client->deleteCard('1');
            $this->fail();
        } catch (SimbilaApiException $e) {
            $this->assertSame(SimbilaApiException::CODE_UNAUTHORIZED, $e->getErrorCode(), 'status-derived code when the body has none');
        }
    }

    public function testInvoicePdfReturnsTheBinaryAndFilename(): void
    {
        $this->mock->append(new Response(200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="9-2026-0412.pdf"'], '%PDF-1.4 binary'));
        $this->mock->append($this->json(404, ['error' => 'Not found']));
        $client = $this->client();

        $pdf = $client->invoicePdf(88213);
        $this->assertSame('/api/v1/invoice/88213/pdf', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('%PDF-1.4 binary', $pdf['content']);
        $this->assertSame('9-2026-0412.pdf', $pdf['filename']);

        $this->expectException(SimbilaApiException::class);
        $client->invoicePdf(1);
    }

    public function testTransportFailureAndUnparsableBody(): void
    {
        $this->mock->append(new ConnectException('Connection refused', new Request('GET', 'x')));
        $this->mock->append(new Response(200, ['Content-Type' => 'application/json'], '<html>oops'));
        $client = $this->client();

        try {
            $client->purchaseStatus('r');
            $this->fail();
        } catch (SimbilaApiException $e) {
            $this->assertSame(0, $e->getStatus());
            $this->assertSame(SimbilaApiException::CODE_TRANSPORT, $e->getErrorCode());
            $this->assertTrue($e->isTransient());
        }
        try {
            $client->purchaseStatus('r');
            $this->fail();
        } catch (SimbilaApiException $e) {
            $this->assertSame(SimbilaApiException::CODE_INVALID_RESPONSE, $e->getErrorCode());
        }
    }

    public function testNormaliseItemsRoundsAndCasts(): void
    {
        $this->assertSame(
            [['text' => 'x', 'price_net' => 82.64, 'vat' => 12.0, 'qty' => 2]],
            AppBillingClient::normaliseItems([['text' => 'x', 'price_net' => '82.6446', 'vat' => '12.04', 'qty' => 2.9]])
        );
    }

    public function testNormaliseItemsKeepsAGrossPrice(): void
    {
        $this->assertSame(
            [['text' => 'x', 'price_gross' => 100.0, 'vat' => 21.0, 'qty' => 1]],
            AppBillingClient::normaliseItems([['text' => 'x', 'price_gross' => '100', 'vat' => '21', 'qty' => 1]]),
            'a gross price is passed through, never divided by the VAT coefficient'
        );
    }

    public function testNormaliseItemsPrefersTheGrossPrice(): void
    {
        $this->assertSame(
            [['text' => 'x', 'price_gross' => 100.0, 'vat' => 21.0, 'qty' => 1]],
            AppBillingClient::normaliseItems([['text' => 'x', 'price_net' => 82.64, 'price_gross' => 100, 'vat' => 21]]),
            'Simbila refuses both keys at once, so only the gross one is sent'
        );
    }
}
