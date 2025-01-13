<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Exceptions\UnexpectedValueException;
use FQL\Functions\Core\SingleFieldAggregateFunction;

class Avg extends SingleFieldAggregateFunction
{
    /**
     * @inheritDoc
     * @return int|float
     * @throws UnexpectedValueException
     */
    public function __invoke(array $items): mixed
    {
        $countItems = count($items);
        if ($countItems === 0) {
            return 0;
        }

        return array_sum(array_map(function ($item) {
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
        }, $items)) / count($items);
    }
}
