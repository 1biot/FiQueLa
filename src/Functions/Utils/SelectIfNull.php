<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class SelectIfNull implements ScalarFunction
{
    public static function name(): string
    {
        return 'IFNULL';
    }

    public static function execute(mixed $value, mixed $fallback): mixed
    {
        return $value ?? $fallback;
    }
}
