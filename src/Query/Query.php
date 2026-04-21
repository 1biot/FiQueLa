<?php

namespace FQL\Query;

use FQL\Conditions\HavingConditionGroup;
use FQL\Conditions\WhereConditionGroup;
use FQL\Enum;
use FQL\Exception\InvalidFormatException;
use FQL\Exception\QueryLogicException;
use FQL\Functions;
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
    use Traits\From {
        from as private traitFrom;
        asFrom as private traitAsFrom;
    }
    use Traits\Into;
    use Traits\Groupable {
        groupBy as private traitGroupBy;
    }
    use Traits\Joinable {
        join as private traitJoin;
        innerJoin as private traitInnerJoin;
        leftJoin as private traitLeftJoin;
        rightJoin as private traitRightJoin;
        fullJoin as private traitFullJoin;
        asJoin as private traitAsJoin;
    }
    use Traits\Limit;
    use Traits\Select {
        distinct as private traitDistinct;
        asSelect as private traitAsSelect;
        select as private traitSelect;
    }
    use Traits\Sortable;
    use Traits\Unionable;
    use Traits\Explain;
    use Traits\Describable;

    private ?Enum\LastClause $lastClause = null;

    /**
     * @implements Interface\Stream<Xml|Json|JsonStream|Yaml|Neon|Csv|Xls>
     */
    public function __construct(private readonly Interface\Stream $stream)
    {
        $this->initializeConditions();
    }

    public function from(string $query): Interface\Query
    {
        $this->lastClause = Enum\LastClause::FROM;
        return $this->traitFrom($query);
    }

    public function join(Interface\Query $query, string $alias = ''): Interface\Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitJoin($query, $alias);
    }

    public function innerJoin(Interface\Query $query, string $alias = ''): Interface\Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitInnerJoin($query, $alias);
    }

    public function leftJoin(Interface\Query $query, string $alias = ''): Interface\Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitLeftJoin($query, $alias);
    }

    public function rightJoin(Interface\Query $query, string $alias = ''): Interface\Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitRightJoin($query, $alias);
    }

    public function fullJoin(Interface\Query $query, string $alias = ''): Interface\Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitFullJoin($query, $alias);
    }

    public function select(string ...$fields): Interface\Query
    {
        $this->lastClause = null;
        return $this->traitSelect(...$fields);
    }

    public function as(string $alias): Interface\Query
    {
        if ($this->lastClause === Enum\LastClause::FROM) {
            $this->traitAsFrom($alias);
        } elseif ($this->lastClause === Enum\LastClause::JOIN) {
            $this->traitAsJoin($alias);
        } else {
            $this->traitAsSelect($alias);
        }
        $this->lastClause = null;
        return $this;
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

    public function describe(): static
    {
        if (!$this->isSelectEmpty()) {
            throw new QueryLogicException('DESCRIBE cannot be combined with SELECT');
        }
        if (!$this->isConditionsEmpty()) {
            throw new QueryLogicException('DESCRIBE cannot be combined with WHERE');
        }
        if (!$this->isGroupableEmpty()) {
            throw new QueryLogicException('DESCRIBE cannot be combined with GROUP BY');
        }
        if (!$this->isSortableEmpty()) {
            throw new QueryLogicException('DESCRIBE cannot be combined with ORDER BY');
        }
        if (!$this->isLimitableEmpty()) {
            throw new QueryLogicException('DESCRIBE cannot be combined with LIMIT');
        }
        if (!$this->isJoinableEmpty()) {
            throw new QueryLogicException('DESCRIBE cannot be combined with JOIN');
        }
        if (!$this->isUnionableEmpty()) {
            throw new QueryLogicException('DESCRIBE cannot be combined with UNION');
        }
        if (!$this->isExplainEmpty()) {
            throw new QueryLogicException('DESCRIBE cannot be combined with EXPLAIN');
        }

        $this->blockSelect();
        $this->blockConditions();
        $this->blockGroupable();
        $this->blockSortable();
        $this->blockLimitable();
        $this->blockJoinable();
        $this->blockUnionable();
        $this->blockExplain();
        $this->enableDescribe();

        return $this;
    }

    public function execute(?string $resultClass = null): Results\ResultsProvider
    {
        if ($this->isDescribeMode()) {
            return new Results\DescribeResult(
                new Results\Stream(
                    $this->stream,
                    false,
                    [],
                    [],
                    $this->getFrom(),
                    new WhereConditionGroup(),
                    new HavingConditionGroup(),
                    [],
                    [],
                    [],
                    null,
                    null,
                )
            );
        }

        $this->validateUnionColumns();

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
            $this->offset,
            $this->getInto(),
            unions: $this->unions,
            fromAlias: $this->getFromAlias(),
        );

        if ($this->explain) {
            return new Results\InMemory($streamResult->explain($this->explainAnalyze));
        }

        $resolvedResultClass = $resultClass ?? (
            $streamResult->hasJoin() || $streamResult->isSortable()
                ? Results\InMemory::class
                : Results\Stream::class
        );

        return $resolvedResultClass === Results\InMemory::class
            ? new Results\InMemory(iterator_to_array($streamResult->getIterator()))
            : $streamResult;
    }

    public function __toString(): string
    {
        if ($this->isDescribeMode()) {
            $source = $this->stream->provideSource();
            $from = $this->getFrom();
            return Interface\Query::DESCRIBE . ' ' . $source
                . ($from !== '' ? '.' . $from : '');
        }

        $queryParts = [];

        // EXPLAIN
        $queryParts[] = $this->explainToString();
        // SELECT
        $queryParts[] = $this->selectToString();
        // FROM
        $queryParts[] = $this->fromToString($this->stream->provideSource());
        // JOIN
        try {
            $queryParts[] = $this->joinsToString();
        } catch (InvalidFormatException $e) {
            $queryParts[] = '[invalid join format]';
        }
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
        // INTO
        $queryParts[] = $this->intoToString();

        return trim(str_replace("\t", "  ", implode('', $queryParts))) . $this->unionsToString();
    }

    private function validateUnionColumns(): void
    {
        if ($this->unions === []) {
            return;
        }

        $mainCount = count($this->selectedFields);
        if ($mainCount === 0) {
            return; // SELECT * — skip validation
        }

        foreach ($this->unions as $i => $union) {
            /** @var self $unionQuery */
            $unionQuery = $union['query'];
            $unionCount = count($unionQuery->selectedFields);
            if ($unionCount === 0) {
                continue; // SELECT * — skip validation
            }
            if ($unionCount !== $mainCount) {
                throw new QueryLogicException(
                    sprintf(
                        'UNION query #%d has %d columns, but main query has %d columns',
                        $i + 1,
                        $unionCount,
                        $mainCount
                    )
                );
            }
        }
    }

    public function provideFileQuery(bool $withQuery = false): FileQuery
    {
        $fileQuery = new FileQuery($this->stream->provideSource());
        if ($withQuery) {
            return $fileQuery->withQuery($this->getFrom());
        }
        return $fileQuery;
    }

    public function isSimpleQuery(): bool
    {
        return $this->isSelectEmpty()
            && $this->isConditionsEmpty()
            && $this->isGroupableEmpty()
            && $this->isSortableEmpty()
            && $this->isLimitableEmpty()
            && $this->isJoinableEmpty()
            && $this->isUnionableEmpty();
    }
}
