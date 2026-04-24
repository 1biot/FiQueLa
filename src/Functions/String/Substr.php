<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\ScalarFunction;

/**
 * SQL-style alias for `SUBSTRING`. Delegates to the canonical implementation
 * so `SUBSTR(name, 1, 2)` and `SUBSTRING(name, 1, 2)` produce the same result.
 */
final class Substr implements ScalarFunction
{
    public static function name(): string
    {
        return 'SUBSTR';
    }

    public static function execute(mixed $value, int $start, ?int $length = null): ?string
    {
        return Substring::execute($value, $start, $length);
    }
}
