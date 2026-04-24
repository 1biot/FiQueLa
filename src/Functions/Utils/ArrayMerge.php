<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class ArrayMerge implements ScalarFunction
{
    public static function name(): string
    {
        return 'ARRAY_MERGE';
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public static function execute(mixed $first, mixed $second): ?array
    {
        if (
            !is_array($first)
            || !is_array($second)
        ) {
            return null;
        }

        return array_merge($first, $second);
    }
}
