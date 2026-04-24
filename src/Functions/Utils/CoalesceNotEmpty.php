<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class CoalesceNotEmpty implements ScalarFunction
{
    public static function name(): string
    {
        return 'COALESCE_NE';
    }

    public static function execute(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if (!empty($value)) {
                return $value;
            }
        }

        return '';
    }
}
