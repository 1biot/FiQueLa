<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\SingleFieldAggregateFunction;

class Min extends SingleFieldAggregateFunction
{
    /**
     * @inheritDoc
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

        return min($values);
    }

    public function initAccumulator(): mixed
    {
        if (!$this->distinct) {
            return null;
        }

        return [
            'value' => null,
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

            if ($accumulator['value'] === null) {
                $accumulator['value'] = $value;
                return $accumulator;
            }

            $accumulator['value'] = min($accumulator['value'], $value);
            return $accumulator;
        }

        if ($accumulator === null) {
            return $value;
        }

        return min($accumulator, $value);
    }

    public function finalize(mixed $accumulator): mixed
    {
        if ($this->distinct) {
            return $accumulator['value'];
        }

        return $accumulator;
    }
}
