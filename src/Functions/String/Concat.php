<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\ScalarFunction;

final class Concat implements ScalarFunction
{
    public static function name(): string
    {
        return 'CONCAT';
    }

    public static function execute(mixed ...$values): string
    {
        return ConcatWS::execute('', ...$values);
    }
}
