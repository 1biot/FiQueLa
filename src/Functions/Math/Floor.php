<?php

namespace FQL\Functions\Math;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\ScalarFunction;

final class Floor implements ScalarFunction
{
    public static function name(): string
    {
        return 'FLOOR';
    }

    /**
     * @throws UnexpectedValueException
     */
    public static function execute(mixed $value): float
    {
        if ($value === null) {
            $value = '';
        }
        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if (!is_numeric($value) && is_string($value)) {
            throw new UnexpectedValueException(
                sprintf('Value is not numeric: %s', $value)
            );
        }

        return floor($value);
    }
}
