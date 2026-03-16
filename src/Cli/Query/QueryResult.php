<?php

namespace FQL\Cli\Query;

/**
 * Unified query result for both local and API execution.
 */
class QueryResult
{
    /**
     * @param array<int, array<string, mixed>> $data
     * @param string[] $headers
     * @param int $totalCount
     * @param float $elapsed Execution time in seconds
     * @param string|null $hash Export hash (API mode only)
     * @param bool $hasMorePages Whether there are more pages to fetch
     */
    public function __construct(
        public readonly array $data,
        public readonly array $headers,
        public readonly int $totalCount,
        public readonly float $elapsed,
        public readonly ?string $hash = null,
        public readonly bool $hasMorePages = false
    ) {
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }
}
