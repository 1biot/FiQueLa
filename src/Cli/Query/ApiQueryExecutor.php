<?php

namespace FQL\Cli\Query;

use FQL\Client\FiQueLaClient;
use FQL\Client\Dto\QueryResult as ApiQueryResult;
use FQL\Query\Debugger;

class ApiQueryExecutor implements QueryExecutorInterface
{
    private FiQueLaClient $client;
    private string $serverName;
    private ?string $file;

    public function __construct(FiQueLaClient $client, string $serverName = '', ?string $file = null)
    {
        $this->client = $client;
        $this->serverName = $serverName;
        $this->file = $file;
    }

    public function execute(string $query, ?int $page = null, ?int $itemsPerPage = null): QueryResult
    {
        $apiResult = $this->client->query(
            $query,
            $this->file,
            $itemsPerPage,
            $page
        );

        return $this->toQueryResult($apiResult);
    }

    public function executeAll(string $query): QueryResult
    {
        // First execute to get the hash and check pagination
        $apiResult = $this->client->query($query, $this->file, 1000, 1);

        if ($apiResult->pagination->hasMultiplePages()) {
            // Export full results via export endpoint
            $exportData = $this->client->export($apiResult->hash, 'json');
            $decoded = json_decode($exportData, true);
            $data = is_array($decoded) ? $decoded : [];
            $headers = !empty($data) ? array_map('strval', array_keys($data[0])) : $apiResult->getHeaders();

            return new QueryResult(
                $data,
                $headers,
                $apiResult->pagination->itemCount,
                $apiResult->elapsed / 1000, // convert ms to seconds
                $apiResult->hash
            );
        }

        return $this->toQueryResult($apiResult);
    }

    public function getModeName(): string
    {
        return 'API';
    }

    public function highlightQuery(string $query): string
    {
        try {
            return Debugger::highlightSQL($query);
        } catch (\Exception) {
            return $query;
        }
    }

    public function getServerName(): string
    {
        return $this->serverName;
    }

    public function getClient(): FiQueLaClient
    {
        return $this->client;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    private function toQueryResult(ApiQueryResult $apiResult): QueryResult
    {
        return new QueryResult(
            $apiResult->data,
            $apiResult->getHeaders(),
            $apiResult->pagination->itemCount,
            $apiResult->elapsed / 1000, // convert ms to seconds
            $apiResult->hash,
            $apiResult->pagination->hasMultiplePages()
        );
    }
}
