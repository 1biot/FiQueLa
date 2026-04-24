<?php

namespace FQL\Functions\String;

use FQL\Enum\Type;
use FQL\Functions\Core\ScalarFunction;

final class Lower implements ScalarFunction
{
    public static function name(): string
    {
        return 'LOWER';
    }

    public static function execute(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }
        return mb_strtolower($value);
    }
}
