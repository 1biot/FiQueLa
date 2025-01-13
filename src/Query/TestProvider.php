<?php

namespace FQL\Query;

use FQL\Exceptions\InvalidArgumentException;
use FQL\Results;
use FQL\Traits\Conditions;
use FQL\Traits\From;
use FQL\Traits\Groupable;
use FQL\Traits\Joinable;
use FQL\Traits\Limit;
use FQL\Traits\Select;
use FQL\Traits\Sortable;

/**
 * Class TestProvider implements traits for Query and empty results when fetching data. We need to test for traits only.
 * @phpstan-import-type SelectedFields from Select
 * @phpstan-import-type ConditionArray from Conditions
 */
class TestProvider implements Query
{
    use Select;
    use From;
    use Groupable;
    use Joinable;
    use Conditions;
    use Sortable;
    use Limit;

    /**
     * @return SelectedFields
     */
    public function getSelectedFields(): array
    {
        return $this->selectedFields;
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

    /**
     * @param string $context
     * @return ConditionArray
     */
    public function getConditions(string $context): array
    {
        return $this->contexts[$context] ?? throw new InvalidArgumentException('Unsupported context');
    }

    public function resetConditions(): Query
    {
        $this->contexts = [
            'where' => [],
            'having' => [],
        ];
        return $this;
    }

    public function execute(?string $resultClass = null): Results\ResultsProvider
    {
        return new Results\InMemory([]);
    }

    public function test(): string
    {
        return '';
    }
}
