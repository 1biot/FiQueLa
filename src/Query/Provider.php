<?php

namespace FQL\Query;

use FQL\Exceptions\QueryLogicException;
use FQL\Stream\Csv;
use FQL\Stream\Json;
use FQL\Stream\JsonStream;
use FQL\Stream\Neon;
use FQL\Stream\Xml;
use FQL\Stream\Yaml;
use FQL\Traits;
use FQL\Results;

final class Provider implements Query, \Stringable
{
    use Traits\Conditions;
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

    public function __construct(private readonly Xml|Json|JsonStream|Yaml|Neon|Csv $stream)
    {
    }

    public function distinct(bool $distinct = true): Query
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
            $this->contexts['where'],
            $this->contexts['having'],
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

    public function test(): string
    {
        return trim((string) $this);
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
        $queryParts[] = $this->conditionsToString('where');
        // GROUP BY
        $queryParts[] = $this->groupByToString();
        // HAVING
        $queryParts[] = $this->conditionsToString('having');
        // ORDER BY
        $queryParts[] = $this->orderByToString();
        // OFFSET
        $queryParts[] = $this->offsetToString();
        // LIMIT
        $queryParts[] = $this->limitToString();

        return str_replace("\t", "  ", implode('', $queryParts));
    }
}
