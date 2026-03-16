<?php

namespace Client;

use FQL\Client\CurlTransport;
use FQL\Client\Exception\ClientException;
use PHPUnit\Framework\TestCase;

class CurlTransportTest extends TestCase
{
    public function testRequestSuccess(): void
    {
        $transport = new CurlTransport(15, 10);

        $response = $transport->request('GET', 'https://example.com');

        $this->assertGreaterThanOrEqual(200, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
        $this->assertStringContainsString('Example Domain', $response->getBody());
    }

    public function testRequestFailureThrowsClientException(): void
    {
        $transport = new CurlTransport(1, 1);

        $this->expectException(ClientException::class);
        $transport->request('GET', 'http://127.0.0.1:1');
    }
}
