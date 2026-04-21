<?php

namespace FQL\Functions\Math;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\ScalarFunction;

final class Add implements ScalarFunction
{
    public static function name(): string
    {
        return 'ADD';
    }

    /**
     * @throws UnexpectedValueException
     */
    public static function execute(mixed ...$values): int|float
    {
        $sum = 0;
        foreach ($values as $value) {
            if (is_string($value)) {
                $value = Type::matchByString($value);
            }

            // Treat empty/null as 0 for additive operations
            if ($value === null || $value === '') {
                $value = 0;
            }

            if (!is_numeric($value) && is_string($value)) {
                throw new UnexpectedValueException(sprintf('Value is not numeric: %s', $value));
            }

            $sum += $value;
        }
        return $sum;
    }
}
