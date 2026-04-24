<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class ArrayFilter implements ScalarFunction
{
    public static function name(): string
    {
        return 'ARRAY_FILTER';
    }

    /**
     * @return array<int, mixed>|null
     */
    public static function execute(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return array_values(array_filter($value));
    }
}
