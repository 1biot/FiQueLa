<?php

namespace FQL\Functions\Aggregate;

use FQL\Functions\Core\SingleFieldAggregateFunction;
use FQL\Interface\Query;

class Count extends SingleFieldAggregateFunction
{
    public function __construct(?string $field = null)
    {
        if ($field === null || $field === '') {
            $field = Query::SELECT_ALL;
        }

        parent::__construct($field);
    }
    public function __invoke(array $items): mixed
    {
        if ($this->field === Query::SELECT_ALL) {
            return count($items);
        }

        return count(
            array_filter(
                array_map(fn(array $item) => $this->getFieldValue($this->field, $item, false) !== null, $items),
                fn($value) => $value
            )
        );
    }

    public function initAccumulator(): mixed
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function accumulate(mixed $accumulator, array $item): mixed
    {
        if ($this->field === Query::SELECT_ALL) {
            return $accumulator + 1;
        }

        $value = $this->getFieldValue($this->field, $item, false);
        if ($value !== null) {
            return $accumulator + 1;
        }

        return $accumulator;
    }

    public function finalize(mixed $accumulator): mixed
    {
        return $accumulator;
    }
}
