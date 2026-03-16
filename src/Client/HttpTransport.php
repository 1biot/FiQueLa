<?php

namespace FQL\Client;

interface HttpTransport
{
    /**
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $url Full URL to request
     * @param array<string, string> $headers Request headers
     * @param string|null $body Request body (JSON string for POST/PUT)
     * @return Response
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): Response;
}
