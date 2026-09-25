<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use TrueApps\SimbilaApi\Exception\SimbilaApiException;
use TrueApps\SimbilaApi\Tests\Support\MocksHttp;
use TrueApps\SimbilaApi\Transport;

final class TransportTest extends TestCase
{
    use MocksHttp;

    protected function setUp(): void
    {
        $this->setUpHttp();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function baseUrls(): array
    {
        return [
            'bare host' => ['https://simbila.test', 'https://simbila.test'],
            'legacy /api' => ['https://simbila.test/api', 'https://simbila.test'],
            'already v1' => ['https://simbila.test/api/v1', 'https://simbila.test'],
            'v1 with trailing slash' => ['https://simbila.test/api/v1/', 'https://simbila.test'],
            'spaces and slash' => [' https://simbila.test/ ', 'https://simbila.test'],
            'upper case' => ['https://simbila.test/API', 'https://simbila.test'],
        ];
    }

    /**
     * @dataProvider baseUrls
     */
    public function testNormaliseBaseUrlStripsALegacyOrV1Suffix(string $given, string $expected): void
    {
        $this->assertSame($expected, Transport::normaliseBaseUrl($given));
    }

    public function testRequestSendsBearerAuthAndTheApiV1Prefix(): void
    {
        $this->mock->append($this->json(200, ['ok' => true]));
        $transport = new Transport('sk_test', 'https://simbila.test', $this->mockHttpClient());

        $result = $transport->request('GET', 'whoami');

        $request = $this->lastRequest();
        $this->assertSame('Bearer sk_test', $request->getHeaderLine('Authorization'));
        $this->assertSame('/api/v1/whoami', $request->getUri()->getPath());
        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
    }

    public function testRequestSendsJsonBodyAndQuery(): void
    {
        $this->mock->append($this->json(201, ['id' => 1]));
        $transport = new Transport('sk_test', 'https://simbila.test', $this->mockHttpClient());

        $transport->request('POST', 'invoice', ['i_type' => 'invoice'], ['appId' => 'lunchdrive']);

        $request = $this->lastRequest();
        $this->assertSame('appId=lunchdrive', $request->getUri()->getQuery());
        $this->assertSame(['i_type' => 'invoice'], json_decode((string) $request->getBody(), true));
    }

    public function testTransportFailureThrowsWithStatusZero(): void
    {
        $this->mock->append(new ConnectException('timeout', new Request('GET', 'x')));
        $transport = new Transport('sk_test', 'https://simbila.test', $this->mockHttpClient());

        try {
            $transport->request('GET', 'whoami');
            $this->fail('expected SimbilaApiException');
        } catch (SimbilaApiException $e) {
            $this->assertSame(0, $e->getStatus());
            $this->assertSame(SimbilaApiException::CODE_TRANSPORT, $e->getErrorCode());
            $this->assertTrue($e->isTransient());
        }
    }

    public function testAnUnparsableBodyThrowsInvalidResponse(): void
    {
        $this->mock->append(new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/html'], '<html>not json</html>'));
        $transport = new Transport('sk_test', 'https://simbila.test', $this->mockHttpClient());

        $this->expectException(SimbilaApiException::class);
        $transport->request('GET', 'whoami');
    }

    public function testAssertOkMapsErrorCodeFromBody(): void
    {
        $transport = new Transport('sk_test', 'https://simbila.test', $this->mockHttpClient());
        try {
            $transport->assertOk(['status' => 402, 'body' => ['error_code' => 'no_card', 'error' => 'No card on file']], [200]);
            $this->fail();
        } catch (SimbilaApiException $e) {
            $this->assertSame('no_card', $e->getErrorCode());
            $this->assertSame('No card on file', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function statusDerivedCodes(): array
    {
        return [
            '401' => [401, SimbilaApiException::CODE_UNAUTHORIZED],
            '403' => [403, SimbilaApiException::CODE_UNAUTHORIZED],
            '404' => [404, SimbilaApiException::CODE_NOT_FOUND],
            '429' => [429, SimbilaApiException::CODE_RATE_LIMITED],
            '500' => [500, 'http_500'],
        ];
    }

    /**
     * @dataProvider statusDerivedCodes
     */
    public function testAssertOkFallsBackToAStatusDerivedCodeWithoutOne(int $status, string $expectedCode): void
    {
        $transport = new Transport('sk_test', 'https://simbila.test', $this->mockHttpClient());
        try {
            $transport->assertOk(['status' => $status, 'body' => []], [200]);
            $this->fail();
        } catch (SimbilaApiException $e) {
            $this->assertSame($expectedCode, $e->getErrorCode());
        }
    }

    public function testAssertOkPassesSilentlyForAnOkStatus(): void
    {
        $transport = new Transport('sk_test', 'https://simbila.test', $this->mockHttpClient());
        $transport->assertOk(['status' => 200, 'body' => []], [200, 201]);
        $this->addToAssertionCount(1);
    }
}
