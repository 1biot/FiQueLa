<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class ArrayCombine implements ScalarFunction
{
    public static function name(): string
    {
        return 'ARRAY_COMBINE';
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public static function execute(mixed $keys, mixed $values): ?array
    {
        if (
            !is_array($keys)
            || !is_array($values)
        ) {
            return null;
        }

        if (self::isAssocStatic($keys)) {
            $keys = array_values($keys);
        }

        if (self::isAssocStatic($values)) {
            $values = array_values($values);
        }

        return array_combine($keys, $values);
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private static function isAssocStatic(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
