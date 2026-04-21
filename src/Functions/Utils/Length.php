<?php

namespace FQL\Functions\Utils;

use FQL\Enum\Type;
use FQL\Functions\Core\ScalarFunction;

final class Length implements ScalarFunction
{
    public static function name(): string
    {
        return 'LENGTH';
    }

    public static function execute(mixed $value): int
    {
        if ($value === null) {
            return 0;
        } elseif (is_array($value)) {
            return count($value);
        } elseif (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }

        return mb_strlen($value);
    }
}
