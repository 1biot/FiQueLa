<?php

namespace FQL\Query;

use FQL\Exception\InvalidFormatException;
use FQL\Exception\QueryLogicException;
use FQL\Exception\UnableOpenFileException;
use FQL\Interface;
use FQL\Results;
use FQL\Stream\Csv;
use FQL\Stream\Json;
use FQL\Stream\JsonStream;
use FQL\Stream\Neon;
use FQL\Stream\Xls;
use FQL\Stream\Xml;
use FQL\Stream\Yaml;
use FQL\Traits;

class Query implements Interface\Query
{
    use Traits\Conditions {
        initialize as initializeConditions;
    }
    use Traits\From;
    use Traits\Groupable {
        groupBy as private traitGroupBy;
    }
    use Traits\Joinable;
    use Traits\Limit;
    use Traits\Select {
        distinct as private traitDistinct;
    }
    use Traits\Sortable;
    use Traits\Explain;

    private ?string $resultClass = null;

    /**
     * @implements Interface\Stream<Xml|Json|JsonStream|Yaml|Neon|Csv|Xls>
     */
    public function __construct(private readonly Interface\Stream $stream)
    {
        $this->initializeConditions();
    }

    public function distinct(bool $distinct = true): Interface\Query
    {
        if ($this->groupByFields !== []) {
            throw new QueryLogicException('DISTINCT is not allowed with GROUP BY clause');
        }

        return $this->traitDistinct($distinct);
    }

    public function groupBy(string ...$fields): Query
    {
        if ($this->distinct) {
            throw new QueryLogicException('GROUP BY is not allowed with DISTINCT clause');
        }

        return $this->traitGroupBy(...$fields);
    }

    /**
     * @throws UnableOpenFileException
     * @throws InvalidFormatException
     */
    public function execute(?string $resultClass = null): Results\ResultsProvider
    {
        $this->resultClass = $resultClass;
        if ($this->explain) {
            $planRows = ExplainPlanBuilder::build($this);
            return new Results\InMemory($planRows);
        }

        $streamResult = new Results\Stream(
            $this->stream,
            $this->distinct,
            $this->selectedFields,
            $this->excludedFields,
            $this->getFrom(),
            $this->whereConditions,
            $this->havingConditions,
            $this->joins,
            $this->groupByFields,
            $this->orderings,
            $this->limit,
            $this->offset
        );

        return match ($resultClass) {
            Results\InMemory::class => new Results\InMemory(iterator_to_array($streamResult->getIterator())),
            Results\Stream::class => $streamResult,
            default => $streamResult->hasJoin() || $streamResult->isSortable()
                ? new Results\InMemory(iterator_to_array($streamResult->getIterator()))
                : $streamResult
        };
    }

    public function __toString(): string
    {
        $queryParts = [];

        // EXPLAIN
        $queryParts[] = $this->explainToString();
        // SELECT
        $queryParts[] = $this->selectToString();
        // FROM
        $queryParts[] = $this->fromToString($this->stream->provideSource());
        // JOIN
        $queryParts[] = $this->joinsToString();
        // WHERE
        $queryParts[] = $this->conditionsToString($this->whereConditions);
        // GROUP BY
        $queryParts[] = $this->groupByToString();
        // HAVING
        $queryParts[] = $this->conditionsToString($this->havingConditions);
        // ORDER BY
        $queryParts[] = $this->orderByToString();
        // OFFSET
        $queryParts[] = $this->offsetToString();
        // LIMIT
        $queryParts[] = $this->limitToString();

        return trim(str_replace("\t", "  ", implode('', $queryParts)));
    }

    public function provideFileQuery(): FileQuery
    {
        return new FileQuery($this->stream->provideSource());
    }

    /**
     * Debug snapshot pro EXPLAIN / debug tooling.
     * @return array<string,mixed>
     */
    public function debugState(): array
    {
        return [
            'stream_class' => $this->stream::class,
            'source' => $this->stream->provideSource(),

            'distinct' => $this->distinct,
            'selectedFields' => $this->selectedFields,
            'excludedFields' => $this->excludedFields,

            'from' => $this->getFrom(),

            'where' => $this->whereConditions,
            'having' => $this->havingConditions,

            'joins' => $this->joins,
            'groupByFields' => $this->groupByFields,
            'orderings' => $this->orderings,

            'limit' => $this->limit,
            'offset' => $this->offset,

            'resultClass' => $this->resultClass,
        ];
    }

}
