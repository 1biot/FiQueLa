<?php

namespace UQL\Functions\Aggregate;

use UQL\Functions\Core\SingleFieldAggregateFunction;
use UQL\Query\Query;

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
                array_map(fn(array $item) => $this->getFieldValue($this->field, $item) !== null, $items),
                fn($value) => $value
            )
        );
    }
}
