<?php

namespace FQL\Client;

use FQL\Client\Dto\AuthToken;
use FQL\Client\Dto\FileSchema;
use FQL\Client\Dto\HistoryEntry;
use FQL\Client\Dto\QueryResult;
use FQL\Client\Exception\AuthenticationException;
use FQL\Client\Exception\ClientException;
use FQL\Client\Exception\NotFoundException;
use FQL\Client\Exception\ServerException;
use FQL\Client\Exception\ValidationException;

class FiQueLaClient
{
    private string $baseUrl;
    private ?string $token;
    private HttpTransport $transport;

    public function __construct(string $baseUrl, ?string $token = null, ?HttpTransport $transport = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->transport = $transport ?? new CurlTransport();
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function hasToken(): bool
    {
        return $this->token !== null && $this->token !== '';
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    // -------------------------------------------------------
    // Auth
    // -------------------------------------------------------

    public function login(string $user, string $secret): AuthToken
    {
        $response = $this->request('POST', '/api/auth/login', [
            'username' => $user,
            'password' => $secret,
        ]);

        return AuthToken::fromArray($response->json());
    }

    public function revoke(string $user, string $secret): AuthToken
    {
        $response = $this->request('POST', '/api/auth/revoke', [
            'username' => $user,
            'password' => $secret,
        ]);

        $data = $response->json();
        // revoke endpoint returns {"revoked": "new_token"}
        return new AuthToken((string) ($data['revoked'] ?? ''));
    }

    // -------------------------------------------------------
    // Health
    // -------------------------------------------------------

    public function ping(): bool
    {
        try {
            $response = $this->request('GET', '/api/v1/ping');
            return $response->isSuccess() && trim($response->getBody()) === 'pong';
        } catch (ClientException) {
            return false;
        }
    }

    // -------------------------------------------------------
    // Files
    // -------------------------------------------------------

    /**
     * @return FileSchema[]
     */
    public function listFiles(): array
    {
        $response = $this->request('GET', '/api/v1/files');
        $data = $response->json();

        $files = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $files[] = FileSchema::fromArray($item);
            }
        }

        return $files;
    }

    public function getFile(string $uuid): FileSchema
    {
        $response = $this->request('GET', sprintf('/api/v1/files/%s', urlencode($uuid)));
        return FileSchema::fromArray($response->json());
    }

    public function updateFile(
        string $uuid,
        ?string $encoding = null,
        ?string $delimiter = null,
        ?string $query = null
    ): FileSchema {
        $body = [];
        if ($encoding !== null) {
            $body['encoding'] = $encoding;
        }
        if ($delimiter !== null) {
            $body['delimiter'] = $delimiter;
        }
        if ($query !== null) {
            $body['query'] = $query;
        }

        $response = $this->request(
            'POST',
            sprintf('/api/v1/files/%s', urlencode($uuid)),
            $body
        );

        $data = $response->json();
        $schemaData = $data['schema'] ?? $data;
        return FileSchema::fromArray(is_array($schemaData) ? $schemaData : []);
    }

    public function deleteFile(string $uuid): bool
    {
        $response = $this->request('DELETE', sprintf('/api/v1/files/%s', urlencode($uuid)));
        return $response->isSuccess();
    }

    // -------------------------------------------------------
    // Query
    // -------------------------------------------------------

    public function query(
        string $query,
        ?string $file = null,
        ?int $limit = null,
        ?int $page = null,
        bool $refresh = false
    ): QueryResult {
        $body = ['query' => $query];

        if ($file !== null) {
            $body['file'] = $file;
        }
        if ($limit !== null) {
            $body['limit'] = $limit;
        }
        if ($page !== null) {
            $body['page'] = $page;
        }
        if ($refresh) {
            $body['refresh'] = true;
        }

        $response = $this->request('POST', '/api/v1/query', $body);
        return QueryResult::fromArray($response->json());
    }

    // -------------------------------------------------------
    // Export
    // -------------------------------------------------------

    /**
     * Export query results by hash. Returns raw response body.
     */
    public function export(string $hash, string $format = 'json', ?string $delimiter = null): string
    {
        $queryParams = ['format' => $format];
        if ($delimiter !== null) {
            $queryParams['delimiter'] = $delimiter;
        }

        $path = sprintf('/api/v1/export/%s?%s', urlencode($hash), http_build_query($queryParams));
        $response = $this->request('GET', $path);
        return $response->getBody();
    }

    // -------------------------------------------------------
    // History
    // -------------------------------------------------------

    /**
     * @return HistoryEntry[]
     */
    public function getHistory(?string $date = null): array
    {
        $path = '/api/v1/history';
        if ($date !== null) {
            $path .= '/' . urlencode($date);
        }

        $response = $this->request('GET', $path);
        $data = $response->json();

        $entries = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $entries[] = HistoryEntry::fromArray($item);
            }
        }

        return $entries;
    }

    // -------------------------------------------------------
    // Internal
    // -------------------------------------------------------

    /**
     * @param string $method
     * @param string $path
     * @param array<string, mixed>|null $body
     * @return Response
     * @throws ClientException
     */
    private function request(string $method, string $path, ?array $body = null): Response
    {
        $url = $this->baseUrl . $path;

        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->hasToken()) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        $encodedBody = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $encodedBody = json_encode($body);
            if ($encodedBody === false) {
                throw new ClientException('Failed to encode request body to JSON.');
            }
        }

        $response = $this->transport->request($method, $url, $headers, $encodedBody);

        $this->handleErrors($response);

        return $response;
    }

    /**
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws NotFoundException
     * @throws ServerException
     */
    private function handleErrors(Response $response): void
    {
        if ($response->isSuccess()) {
            return;
        }

        $data = $response->json();
        $errorMessage = (string) ($data['error'] ?? $response->getBody());

        match ($response->getStatusCode()) {
            401 => throw new AuthenticationException($errorMessage),
            404 => throw new NotFoundException($errorMessage),
            422 => throw new ValidationException($errorMessage, $data),
            default => null,
        };

        if ($response->isServerError()) {
            throw new ServerException($errorMessage);
        }

        if ($response->isClientError()) {
            throw new ClientException($errorMessage);
        }
    }
}
