<?php

namespace Cli\Interactive\Support;

use FQL\Cli\Query\QueryExecutorInterface;
use FQL\Cli\Query\QueryResult;

class FakePagedExecutor implements QueryExecutorInterface
{
    /** @var array<int, array<int, array<string, mixed>>> */
    private array $pages;
    private int $totalCount;

    /**
     * @param array<int, array<int, array<string, mixed>>> $pages
     */
    public function __construct(array $pages, int $totalCount)
    {
        $this->pages = $pages;
        $this->totalCount = $totalCount;
    }

    public function execute(string $query, ?int $page = null, ?int $itemsPerPage = null): QueryResult
    {
        $page = $page ?? 1;
        $data = $this->pages[$page] ?? [];
        $headers = $data !== [] ? array_keys($data[0]) : [];
        $hasMorePages = count($this->pages) > 1;

        return new QueryResult($data, $headers, $this->totalCount, 0.01, null, $hasMorePages);
    }

    public function executeAll(string $query): QueryResult
    {
        $merged = [];
        foreach ($this->pages as $pageData) {
            $merged = array_merge($merged, $pageData);
        }
        $headers = $merged !== [] ? array_keys($merged[0]) : [];
        return new QueryResult($merged, $headers, count($merged), 0.01);
    }

    public function getModeName(): string
    {
        return 'FAKE';
    }

    public function highlightQuery(string $query): string
    {
        return $query;
    }
}
