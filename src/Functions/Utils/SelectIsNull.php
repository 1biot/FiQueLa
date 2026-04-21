<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class SelectIsNull implements ScalarFunction
{
    public static function name(): string
    {
        return 'ISNULL';
    }

    public static function execute(mixed $value): bool
    {
        return $value === null;
    }
}
