<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use TrueApps\SimbilaApi\Exception\SimbilaApiException;

/**
 * Bearer-authenticated JSON transport shared by every resource client
 * ({@see AppBillingClient}, {@see PayMeClient}, {@see BillingClient},
 * {@see InvoiceClient}): builds `{$baseUrl}/api/v1/{$path}`, sends
 * `Authorization: Bearer <apiKey>`, decodes the JSON body and maps a non-2xx
 * response or a transport failure to {@see SimbilaApiException}.
 *
 * Framework-agnostic on purpose: no caching, no config resolution - an
 * application wraps this (see the `trueapps/lunchdrive-php` adapters) for
 * that. The API key is never logged.
 */
final class Transport
{
    public const API_PREFIX = '/api/v1';
    public const DEFAULT_TIMEOUT = 20.0;

    private ClientInterface $http;
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $apiKey, string $baseUrl = 'https://simbila.com', ?ClientInterface $http = null, float $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = self::normaliseBaseUrl($baseUrl);
        $this->http = $http ?? new Client(['timeout' => $timeout, 'connect_timeout' => 10, 'http_errors' => false]);
    }

    /**
     * Strips a trailing `/api` or `/api/v1` so a caller can pass either the bare
     * host or a full legacy/v1 URL (the transport always appends `/api/v1` itself).
     */
    public static function normaliseBaseUrl(string $baseUrl): string
    {
        $url = rtrim(trim($baseUrl), '/');
        if (preg_match('~^(.*)/api/v1$~i', $url, $m) || preg_match('~^(.*)/api$~i', $url, $m)) {
            $url = $m[1];
        }
        return rtrim($url, '/');
    }

    /**
     * @return array{status: int, body: array<string, mixed>, raw: string, headers: array<string, string[]>}
     * @throws SimbilaApiException `CODE_TRANSPORT` (status 0) on connection failure,
     *   `CODE_INVALID_RESPONSE` on an unparsable non-binary body
     */
    public function request(string $method, string $path, ?array $json = null, array $query = [], bool $binary = false): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => $binary ? 'application/pdf, application/json' : 'application/json',
            ],
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }
        if ($query !== []) {
            $options['query'] = $query;
        }
        $url = $this->baseUrl . self::API_PREFIX . '/' . ltrim($path, '/');

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                // a client configured with http_errors = true: the error response is still an answer
                return $this->decode($e->getResponse(), $binary);
            }
            throw new SimbilaApiException(0, SimbilaApiException::CODE_TRANSPORT, 'Simbila request failed: ' . $e->getMessage(), [], $e);
        } catch (GuzzleException $e) {
            throw new SimbilaApiException(0, SimbilaApiException::CODE_TRANSPORT, 'Simbila request failed: ' . $e->getMessage(), [], $e);
        }

        return $this->decode($response, $binary);
    }

    /**
     * @return array{status: int, body: array<string, mixed>, raw: string, headers: array<string, string[]>}
     */
    private function decode(ResponseInterface $response, bool $binary): array
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        $body = [];
        if ($raw !== '' && (!$binary || str_contains($contentType, 'json'))) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            } elseif (!$binary) {
                throw new SimbilaApiException($status, SimbilaApiException::CODE_INVALID_RESPONSE, 'Simbila returned an unparsable response (HTTP ' . $status . ')');
            }
        }
        return ['status' => $status, 'body' => $body, 'raw' => $raw, 'headers' => $response->getHeaders()];
    }

    /**
     * Raises {@see SimbilaApiException} for a result outside `$okStatuses`, mapping
     * `error_code`/`error` from the body when present, else a status-derived code.
     *
     * @param array{status: int, body: array<string, mixed>} $result
     * @param int[] $okStatuses
     * @throws SimbilaApiException
     */
    public function assertOk(array $result, array $okStatuses): void
    {
        if (in_array($result['status'], $okStatuses, true)) {
            return;
        }
        $body = $result['body'];
        $code = (string) ($body['error_code'] ?? '');
        if ($code === '') {
            $code = match (true) {
                $result['status'] === 401 || $result['status'] === 403 => SimbilaApiException::CODE_UNAUTHORIZED,
                $result['status'] === 404 => SimbilaApiException::CODE_NOT_FOUND,
                $result['status'] === 429 => SimbilaApiException::CODE_RATE_LIMITED,
                default => 'http_' . $result['status'],
            };
        }
        $message = (string) ($body['error'] ?? $body['message'] ?? $body['result'] ?? ('Simbila returned HTTP ' . $result['status']));
        throw new SimbilaApiException($result['status'], $code, $message, $body);
    }
}
