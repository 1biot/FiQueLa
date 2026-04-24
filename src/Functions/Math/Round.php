<?php

namespace FQL\Functions\Math;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\ScalarFunction;

final class Round implements ScalarFunction
{
    public static function name(): string
    {
        return 'ROUND';
    }

    /**
     * @throws UnexpectedValueException
     */
    public static function execute(mixed $value, int $precision = 0): float
    {
        if ($value === null) {
            $value = '';
        }
        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if ($value === '') {
            $value = 0;
        }

        if (!is_numeric($value) && is_string($value)) {
            throw new UnexpectedValueException(
                sprintf('Value is not numeric: %s', $value)
            );
        }

        return round($value, $precision);
    }
}
