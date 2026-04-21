<?php

namespace FQL\Functions\String;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\ScalarFunction;

final class Explode implements ScalarFunction
{
    public static function name(): string
    {
        return 'EXPLODE';
    }

    /**
     * @throws UnexpectedValueException
     * @return string[]
     */
    public static function execute(mixed $value, string $separator = ','): array
    {
        if (!is_string($value) && $value !== null) {
            throw new UnexpectedValueException('Value is not a string');
        }
        $value = $value ?? '';
        if ($separator === '') {
            return str_split($value);
        }

        return explode($separator, $value);
    }
}
