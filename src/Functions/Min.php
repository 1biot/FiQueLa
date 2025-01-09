<?php

namespace UQL\Functions;

use UQL\Enum\Type;
use UQL\Exceptions\UnexpectedValueException;

class Min extends SingleFieldAggregateFunction
{
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
}
