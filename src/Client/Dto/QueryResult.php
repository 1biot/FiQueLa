<?php

namespace FQL\Client\Dto;

class QueryResult
{
    /**
     * @param string $query
     * @param string $file
     * @param string $hash
     * @param array<int, array<string, mixed>> $data
     * @param float $elapsed
     * @param Pagination $pagination
     */
    public function __construct(
        public readonly string $query,
        public readonly string $file,
        public readonly string $hash,
        public readonly array $data,
        public readonly float $elapsed,
        public readonly Pagination $pagination
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            query: (string) ($data['query'] ?? ''),
            file: (string) ($data['file'] ?? ''),
            hash: (string) ($data['hash'] ?? ''),
            data: is_array($data['data'] ?? null) ? $data['data'] : [],
            elapsed: (float) ($data['elapsed'] ?? 0.0),
            pagination: Pagination::fromArray(
                is_array($data['pagination'] ?? null) ? $data['pagination'] : []
            )
        );
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        if (empty($this->data)) {
            return [];
        }

        return array_map('strval', array_keys($this->data[0]));
    }
}
