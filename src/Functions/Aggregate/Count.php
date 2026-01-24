<?php

namespace FQL\Functions\Aggregate;

use FQL\Exception\InvalidArgumentException;
use FQL\Functions\Core\SingleFieldAggregateFunction;
use FQL\Interface\Query;

class Count extends SingleFieldAggregateFunction
{
    public function __construct(?string $field = null, bool $distinct = false)
    {
        if ($field === null || $field === '') {
            $field = Query::SELECT_ALL;
        }

        parent::__construct($field, $distinct);

        if ($this->distinct && $this->field === Query::SELECT_ALL) {
            throw new InvalidArgumentException('DISTINCT is not supported with COUNT(*)');
        }
    }
    public function __invoke(array $items): mixed
    {
        if ($this->field === Query::SELECT_ALL) {
            return count($items);
        }

        $seen = $this->distinct ? $this->resetDistinctSeen() : [];
        $count = 0;
        foreach ($items as $item) {
            $value = $this->getFieldValue($this->field, $item, false);
            if ($value === null) {
                continue;
            }

            if (!$this->isDistinctValue($value, $seen)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    public function initAccumulator(): mixed
    {
        if (!$this->distinct) {
            return 0;
        }

        return [
            'value' => 0,
            'seen' => [],
        ];
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
            if ($this->distinct) {
                if (!$this->isDistinctValue($value, $accumulator['seen'])) {
                    return $accumulator;
                }

                $accumulator['value']++;
                return $accumulator;
            }

            return $accumulator + 1;
        }

        return $accumulator;
    }

    public function finalize(mixed $accumulator): mixed
    {
        if ($this->distinct) {
            return $accumulator['value'];
        }

        return $accumulator;
    }
}
