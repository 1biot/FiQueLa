<?php

namespace UQL\Query;

use UQL\Exceptions\InvalidArgumentException;
use UQL\Traits\Conditions;
use UQL\Traits\From;
use UQL\Traits\Joinable;
use UQL\Traits\Limit;
use UQL\Traits\Select;
use UQL\Traits\Sortable;

/**
 * Class TestProvider implements traits for Query and empty results when fetching data. We need to test for traits only.
 * @phpstan-import-type SelectedFields from Select
 * @phpstan-import-type ConditionArray from Conditions
 */
class TestProvider implements Query
{
    use Select;
    use From;
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

    public function fetchAll(?string $dto = null): \Generator
    {
        yield [];
    }

    public function fetchNth(int|string $n, ?string $dto = null): \Generator
    {
        yield [];
    }

    public function fetch(?string $dto = null): mixed
    {
        return $dto ? new \stdClass() : [];
    }

    public function fetchSingle(string $key): mixed
    {
        return null;
    }

    public function sum(string $key): float
    {
        return 0;
    }

    public function avg(string $key, int $decimalPlaces = 2): float
    {
        return 0;
    }

    public function test(): string
    {
        return '';
    }

    public function count(): int
    {
        return 0;
    }

    public function min(string $key): float
    {
        return 0;
    }

    public function max(string $key): float
    {
        return 0;
    }
}
