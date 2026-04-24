<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\ScalarFunction;

final class Locate implements ScalarFunction
{
    public static function name(): string
    {
        return 'LOCATE';
    }

    public static function execute(mixed $needle, mixed $haystack, ?int $position = null): ?int
    {
        // Only scalar or null values are allowed
        if ((!is_scalar($haystack) && $haystack !== null) || (!is_scalar($needle) && $needle !== null)) {
            return null;
        }

        // Ensure both are strings
        $haystack = (string) $haystack;
        $needle = (string) $needle;

        // MySQL uses 1-based indexing
        $offset = max(1, $position ?? 1) - 1;

        // Search within the haystack starting at offset
        $found = mb_strpos(mb_substr($haystack, $offset), $needle);

        // Return 0 if not found, otherwise actual position (1-based)
        return $found === false ? 0 : $found + $offset + 1;
    }
}
