<?php

namespace FQL\Client;

use FQL\Client\Exception\ClientException;

class CurlTransport implements HttpTransport
{
    private int $timeout;
    private int $connectTimeout;

    public function __construct(int $timeout = 30, int $connectTimeout = 10)
    {
        if (!extension_loaded('curl')) {
            throw new ClientException('The curl extension is required for the FiQueLa API client.');
        }

        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param string|null $body
     * @return Response
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new ClientException('Failed to initialize cURL session.');
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
            ]);

            $curlHeaders = [];
            foreach ($headers as $name => $value) {
                $curlHeaders[] = sprintf('%s: %s', $name, $value);
            }

            if (!empty($curlHeaders)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            }

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseHeaders = [];
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $headerLine) use (&$responseHeaders) {
                $len = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
                return $len;
            });

            $responseBody = curl_exec($ch);

            if ($responseBody === false) {
                $error = curl_error($ch);
                $errno = curl_errno($ch);
                throw new ClientException(
                    sprintf('cURL request failed: [%d] %s', $errno, $error)
                );
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            return new Response($statusCode, $responseHeaders, (string) $responseBody);
        } finally {
            // curl handle is automatically closed when it goes out of scope (PHP 8.0+)
            unset($ch);
        }
    }
}
