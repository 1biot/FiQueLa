<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Functions;
use FQL\Interface\Query;
use FQL\Results;
use FQL\Traits\Conditions;
use FQL\Traits\From;
use FQL\Traits\Groupable;
use FQL\Traits\Into;
use FQL\Traits\Joinable;
use FQL\Traits\Limit;
use FQL\Traits\Select;
use FQL\Traits\Sortable;
use FQL\Traits\Describable;
use FQL\Traits\Unionable;

/**
 * Class TestProvider implements traits for Query and empty results when fetching data. We need to test for traits only.
 *
 * Excluded from coverage — this is a test scaffolding class used to exercise
 * the trait composition in isolation, not production code.
 *
 * @phpstan-import-type SelectedFields from Select
 * @codeCoverageIgnore
 */
class TestProvider implements Query
{
    use Select {
        asSelect as private traitAsSelect;
        select as private traitSelect;
    }
    use From {
        from as private traitFrom;
        asFrom as private traitAsFrom;
    }
    use Into;
    use Groupable;
    use Joinable {
        join as private traitJoin;
        innerJoin as private traitInnerJoin;
        leftJoin as private traitLeftJoin;
        rightJoin as private traitRightJoin;
        fullJoin as private traitFullJoin;
        asJoin as private traitAsJoin;
    }
    use Conditions {
        initialize as initializeConditions;
    }
    use Sortable;
    use Unionable;
    use Limit;
    use Describable;

    private ?Enum\LastClause $lastClause = null;

    public function select(string ...$fields): Query
    {
        $this->lastClause = null;
        return $this->traitSelect(...$fields);
    }

    public function from(string $query): Query
    {
        $this->lastClause = Enum\LastClause::FROM;
        return $this->traitFrom($query);
    }

    public function join(Query $query, string $alias = ''): Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitJoin($query, $alias);
    }

    public function innerJoin(Query $query, string $alias = ''): Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitInnerJoin($query, $alias);
    }

    public function leftJoin(Query $query, string $alias = ''): Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitLeftJoin($query, $alias);
    }

    public function rightJoin(Query $query, string $alias = ''): Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitRightJoin($query, $alias);
    }

    public function fullJoin(Query $query, string $alias = ''): Query
    {
        $this->lastClause = Enum\LastClause::JOIN;
        return $this->traitFullJoin($query, $alias);
    }

    public function as(string $alias): Query
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

    /**
     * @return SelectedFields
     */
    public function getSelectedFields(): array
    {
        return $this->selectedFields;
    }

    /**
     * @return string[]
     */
    public function getExcludedFields(): array
    {
        return $this->excludedFields;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    public function getLimitAndOffset(): array
    {
        return [$this->getLimit(), $this->getOffset()];
    }

    public function getFromSource(): string
    {
        return $this->getFrom();
    }

    public function getFromAliasValue(): ?string
    {
        return $this->getFromAlias();
    }

    public function resetConditions(): Query
    {
        $this->initializeConditions();
        return $this;
    }

    public function execute(?string $resultClass = null): Results\ResultsProvider
    {
        return new Results\InMemory([]);
    }

    public function explain(): Query
    {
        return $this;
    }

    public function explainAnalyze(): Query
    {
        return $this;
    }

    public function describe(): static
    {
        $this->enableDescribe();
        return $this;
    }

    public function test(): string
    {
        return '';
    }

    public function __toString(): string
    {
        return '';
    }

    public function provideFileQuery(bool $withQuery = false): FileQuery
    {
        return new FileQuery($this->getFromSource());
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
