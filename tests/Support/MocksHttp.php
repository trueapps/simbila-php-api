<?php

declare(strict_types=1);

namespace TrueApps\SimbilaApi\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Shared Guzzle mock-handler wiring for the resource-client tests.
 */
trait MocksHttp
{
    private MockHandler $mock;
    /** @var array<int, array{request: Request}> */
    private array $history = [];

    protected function setUpHttp(): void
    {
        $this->mock = new MockHandler();
        $this->history = [];
    }

    protected function mockHttpClient(): Client
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        return new Client(['handler' => $stack]);
    }

    protected function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    protected function lastRequest(): Request
    {
        return $this->history[count($this->history) - 1]['request'];
    }
}
