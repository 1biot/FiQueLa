<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\ScalarFunction;

final class LeftPad implements ScalarFunction
{
    public static function name(): string
    {
        return 'LPAD';
    }

    public static function execute(mixed $value, int $length, string $pad = ' '): ?string
    {
        if ($value === null) {
            $value = '';
        }
        if (is_scalar($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        return str_pad($value, $length, $pad, STR_PAD_LEFT);
    }
}
