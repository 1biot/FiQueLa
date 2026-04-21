<?php

namespace FQL\Functions\String;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\ScalarFunction;

final class Implode implements ScalarFunction
{
    public static function name(): string
    {
        return 'IMPLODE';
    }

    /**
     * @throws UnexpectedValueException
     */
    public static function execute(mixed $value, string $separator = ','): string
    {
        if (!is_array($value) && !is_scalar($value)) {
            throw new UnexpectedValueException('Value is not an array or scalar');
        }

        return is_scalar($value) ? (string) $value : implode($separator, $value);
    }
}
