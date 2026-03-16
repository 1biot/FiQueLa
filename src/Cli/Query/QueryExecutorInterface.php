<?php

namespace FQL\Cli\Query;

interface QueryExecutorInterface
{
    /**
     * Execute a query and return the result.
     *
     * @param string $query The FQL/SQL-like query
     * @param int|null $page Page number for paginated results
     * @param int|null $itemsPerPage Items per page
     * @return QueryResult
     */
    public function execute(string $query, ?int $page = null, ?int $itemsPerPage = null): QueryResult;

    /**
     * Execute a query and return ALL results (no pagination).
     * Used for non-interactive JSON output.
     *
     * @param string $query The FQL/SQL-like query
     * @return QueryResult
     */
    public function executeAll(string $query): QueryResult;

    /**
     * Get mode name for display in header.
     */
    public function getModeName(): string;

    /**
     * Highlight query for interactive display.
     */
    public function highlightQuery(string $query): string;
}
