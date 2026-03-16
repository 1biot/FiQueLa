<?php

namespace FQL\Cli\Query;

use FQL\Enum\Format;
use FQL\Interface\Query;
use FQL\Query\Debugger;
use FQL\Query\Provider;
use FQL\Sql\Sql;
use FQL\Stream;

class LocalQueryExecutor implements QueryExecutorInterface
{
    private ?string $file;
    private ?string $fileType;
    private string $delimiter;
    private string $encoding;

    public function __construct(
        ?string $file = null,
        ?string $fileType = null,
        string $delimiter = ',',
        string $encoding = 'utf-8'
    ) {
        $this->file = $file;
        $this->fileType = $fileType;
        $this->delimiter = $delimiter;
        $this->encoding = $encoding;
    }

    public function execute(string $query, ?int $page = null, ?int $itemsPerPage = null): QueryResult
    {
        $timerStart = microtime(true);
        $queryObj = $this->provideQuery($query);

        $results = $queryObj->execute();
        if (!$results->exists()) {
            $elapsed = microtime(true) - $timerStart;
            return new QueryResult([], [], 0, $elapsed);
        }

        $totalCount = $results->count();
        /** @var array<string, mixed> $firstRow */
        $firstRow = $results->fetch();
        $headers = array_keys($firstRow);

        // Apply pagination if requested
        if ($page !== null && $itemsPerPage !== null && $totalCount > $itemsPerPage) {
            $offset = ($page - 1) * $itemsPerPage;
            $queryObj->limit($itemsPerPage, $offset);
        }

        /** @var array<int, array<string, mixed>> $data */
        $data = iterator_to_array($queryObj->execute()->getIterator());
        $elapsed = microtime(true) - $timerStart;

        $hasMorePages = false;
        if ($page !== null && $itemsPerPage !== null) {
            $pageCount = (int) ceil($totalCount / $itemsPerPage);
            $hasMorePages = $pageCount > 1;
        }

        return new QueryResult($data, $headers, $totalCount, $elapsed, null, $hasMorePages);
    }

    public function executeAll(string $query): QueryResult
    {
        $timerStart = microtime(true);
        $queryObj = $this->provideQuery($query);

        $results = $queryObj->execute();
        if (!$results->exists()) {
            $elapsed = microtime(true) - $timerStart;
            return new QueryResult([], [], 0, $elapsed);
        }

        /** @var array<string, mixed> $firstRow */
        $firstRow = $results->fetch();
        $headers = array_keys($firstRow);
        /** @var array<int, array<string, mixed>> $data */
        $data = iterator_to_array($queryObj->execute()->getIterator());
        $elapsed = microtime(true) - $timerStart;

        return new QueryResult($data, $headers, count($data), $elapsed);
    }

    public function getModeName(): string
    {
        return 'LOCAL';
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Get highlighted SQL for display.
     */
    public function highlightQuery(string $query): string
    {
        try {
            return Debugger::highlightSQL($query);
        } catch (\Exception) {
            return $query;
        }
    }

    private function provideQuery(string $query): Query
    {
        if ($this->file !== null) {
            $stream = Stream\Provider::fromFile(
                $this->file,
                Format::tryFrom($this->fileType ?? '')
            );

            if ($stream instanceof Stream\Csv) {
                $stream->setDelimiter($this->delimiter);
                $stream->setInputEncoding($this->encoding);
            } elseif ($stream instanceof Stream\Xml) {
                $stream->setInputEncoding($this->encoding);
            }

            return (new Sql(trim($query)))->parseWithQuery($stream->query());
        }

        return Provider::fql($query);
    }
}
