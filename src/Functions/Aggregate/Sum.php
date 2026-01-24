<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\SingleFieldAggregateFunction;

class Sum extends SingleFieldAggregateFunction
{
    /**
     * @inheritDoc
     * @return float|int
     * @throws UnexpectedValueException
     */
    public function __invoke(array $items): mixed
    {
        $seen = $this->distinct ? $this->resetDistinctSeen() : [];
        $values = [];

        foreach ($items as $item) {
            $value = $this->getFieldValue($this->field, $item);
            if (is_string($value)) {
                $value = Type::matchByString($value);
            }

            if ($value === '') {
                $value = 0;
            }

            if (!is_numeric($value)) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Field "%s" value is not numeric: %s',
                        $this->field,
                        $value
                    )
                );
            }

            if (!$this->isDistinctValue($value, $seen)) {
                continue;
            }

            $values[] = $value;
        }

        return array_sum($values);
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
        $value = $this->getFieldValue($this->field, $item);
        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if ($value === '') {
            $value = 0;
        }

        if (!is_numeric($value)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Field "%s" value is not numeric: %s',
                    $this->field,
                    $value
                )
            );
        }

        if ($this->distinct) {
            if (!$this->isDistinctValue($value, $accumulator['seen'])) {
                return $accumulator;
            }

            $accumulator['value'] += $value;
            return $accumulator;
        }

        return $accumulator + $value;
    }

    public function finalize(mixed $accumulator): mixed
    {
        if ($this->distinct) {
            return $accumulator['value'];
        }

        return $accumulator;
    }
}
