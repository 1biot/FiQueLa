<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\ScalarFunction;

final class Substring implements ScalarFunction
{
    public static function name(): string
    {
        return 'SUBSTRING';
    }

    public static function execute(mixed $value, int $start, ?int $length = null): ?string
    {
        if (!(is_scalar($value) || $value === null)) {
            return null;
        }

        return mb_substr((string) $value, $start, $length);
    }
}
