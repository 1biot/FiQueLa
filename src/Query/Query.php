<?php

namespace FQL\Query;

use FQL\Exceptions\QueryLogicException;
use FQL\Interfaces;
use FQL\Results;
use FQL\Stream\Csv;
use FQL\Stream\Json;
use FQL\Stream\JsonStream;
use FQL\Stream\Neon;
use FQL\Stream\Xml;
use FQL\Stream\Yaml;
use FQL\Traits;

class Query implements Interfaces\Query
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

    /**
     * @implements Interfaces\Stream<Xml|Json|JsonStream|Yaml|Neon|Csv>
     */
    public function __construct(private readonly Interfaces\Stream $stream)
    {
        $this->initializeConditions();
    }

    public function distinct(bool $distinct = true): Interfaces\Query
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

    public function execute(?string $resultClass = null): Results\ResultsProvider
    {
        $streamResult = new Results\Stream(
            $this->stream,
            $this->distinct,
            $this->selectedFields,
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
            default => $streamResult->hasJoin() || $streamResult->isSortable() || $streamResult->isGroupable()
                ? new Results\InMemory(iterator_to_array($streamResult->getIterator()))
                : $streamResult
        };
    }

    public function __toString(): string
    {
        $queryParts = [];

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
}
