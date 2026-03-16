<?php

namespace Client;

use FQL\Client\HttpTransport;
use FQL\Client\Response;

/**
 * Mock transport for unit testing the API client.
 * Allows pre-configuring responses and recording requests.
 */
class MockTransport implements HttpTransport
{
    /** @var Response[] */
    private array $responses = [];

    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    private array $requests = [];

    public function addResponse(Response $response): void
    {
        $this->responses[] = $response;
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        if (empty($this->responses)) {
            return new Response(500, [], '{"error": "No mock response configured"}');
        }

        return array_shift($this->responses);
    }

    /**
     * @return array<int, array{method: string, url: string, headers: array<string, string>, body: string|null}>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * @return array{method: string, url: string, headers: array<string, string>, body: string|null}|null
     */
    public function getLastRequest(): ?array
    {
        if (empty($this->requests)) {
            return null;
        }

        return end($this->requests);
    }

    public function getRequestCount(): int
    {
        return count($this->requests);
    }

    public function reset(): void
    {
        $this->responses = [];
        $this->requests = [];
    }
}
