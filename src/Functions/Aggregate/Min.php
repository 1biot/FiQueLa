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
        return min(
            array_map(function ($item) {
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

                return $value;
            }, $items)
        );
    }

    public function initAccumulator(): mixed
    {
        return null;
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

        if ($accumulator === null) {
            return $value;
        }

        return min($accumulator, $value);
    }

    public function finalize(mixed $accumulator): mixed
    {
        return $accumulator;
    }
}
