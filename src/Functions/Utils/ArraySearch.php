<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class ArraySearch implements ScalarFunction
{
    public static function name(): string
    {
        return 'ARRAY_SEARCH';
    }

    public static function execute(mixed $haystack, mixed $needle): mixed
    {
        if (!is_array($haystack)) {
            return null;
        }

        return array_search($needle, $haystack, true);
    }
}
