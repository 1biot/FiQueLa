<?php

namespace FQL\Client\Dto;

class HistoryEntry
{
    public function __construct(
        public readonly string $createdAt,
        public readonly string $query,
        public readonly string $runs
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            createdAt: (string) ($data['created_at'] ?? ''),
            query: (string) ($data['query'] ?? ''),
            runs: (string) ($data['runs'] ?? '')
        );
    }
}
