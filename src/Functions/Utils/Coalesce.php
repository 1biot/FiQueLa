<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class Coalesce implements ScalarFunction
{
    public static function name(): string
    {
        return 'COALESCE';
    }

    public static function execute(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if ($value !== null) {
                return $value;
            }
        }

        return '';
    }
}
