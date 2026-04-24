<?php

namespace FQL\Functions\Math;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\ScalarFunction;

final class Mod implements ScalarFunction
{
    public static function name(): string
    {
        return 'MOD';
    }

    /**
     * @throws UnexpectedValueException
     */
    public static function execute(mixed $value, int $divisor): int|float
    {
        if ($divisor === 0) {
            throw new UnexpectedValueException('Divisor cannot be zero');
        }

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

        return fmod($value, $divisor);
    }
}
