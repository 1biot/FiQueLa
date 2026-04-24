<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\ScalarFunction;

final class ConcatWS implements ScalarFunction
{
    public static function name(): string
    {
        return 'CONCAT_WS';
    }

    public static function execute(mixed $separator, mixed ...$values): string
    {
        $separator = (string) ($separator ?? '');
        $parts = [];
        foreach ($values as $value) {
            $parts[] = $value ?? '';
        }
        return implode($separator, $parts);
    }
}
