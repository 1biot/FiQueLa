<?php

namespace UQL\Functions;

use UQL\Enum\Type;
use UQL\Exceptions\InvalidArgumentException;

class Max extends SingleFieldAggregateFunction
{
    public function __invoke(array $items): mixed
    {
        return max(
            array_map(function ($item) {
                $value = $this->getFieldValue($this->field, $item);
                if (is_string($value)) {
                    $value = Type::matchByString($value);
                }

                if (!is_numeric($value)) {
                    throw new InvalidArgumentException(
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
