<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi\Exception;

use RuntimeException;
use Throwable;

/**
 * Error of any `/api/v1` call: the HTTP status (0 for a transport failure -
 * connection refused, timeout, an unparsable response body), a machine-readable
 * error code and the remaining decoded body.
 *
 * `errorCode` is Simbila's own `error_code` when the response carried one
 * (app-billing, most domain errors); callers that get none fall back to a
 * status-derived code (`unauthorized`, `not_found`, `rate_limited`, `http_<n>`)
 * or one of the {@see CODE_*} transport codes.
 */
class SimbilaApiException extends RuntimeException
{
    public const CODE_TRANSPORT = 'transport_error';
    public const CODE_INVALID_RESPONSE = 'invalid_response';
    public const CODE_NOT_CONFIGURED = 'not_configured';
    public const CODE_UNAUTHORIZED = 'unauthorized';
    public const CODE_NOT_FOUND = 'not_found';
    public const CODE_RATE_LIMITED = 'rate_limited';

    private int $status;
    private string $errorCode;
    /** @var array<string, mixed> */
    private array $body;

    /**
     * @param array<string, mixed> $body decoded response body (may be empty)
     */
    public function __construct(int $status, string $errorCode, string $message, array $body = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->body = $body;
    }

    /**
     * HTTP status of the response, 0 for a transport failure.
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Machine-readable code: Simbila's own `error_code`, or one of the {@see CODE_*}
     * constants for a transport failure / status-derived fallback.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed> decoded response body, `error`/`error_code` included
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function extra(string $key, $default = null)
    {
        return array_key_exists($key, $this->body) ? $this->body[$key] : $default;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    public function isUnauthorized(): bool
    {
        return $this->status === 401 || $this->status === 403;
    }

    /**
     * Whether the same request may succeed later (transport failure, 429, 5xx).
     */
    public function isTransient(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }

    /**
     * Seconds to wait before retrying a `429 rate_limited` response, when Simbila sent one.
     */
    public function getRetryAfter(): ?int
    {
        $value = $this->extra('retry_after');
        return is_numeric($value) ? max(1, (int) $value) : null;
    }
}
