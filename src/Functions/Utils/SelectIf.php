<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class SelectIf implements ScalarFunction
{
    public static function name(): string
    {
        return 'IF';
    }

    public static function execute(mixed $condition, mixed $thenValue, mixed $elseValue): mixed
    {
        return $condition ? $thenValue : $elseValue;
    }
}
