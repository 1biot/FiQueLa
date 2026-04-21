<?php

namespace FQL\Functions\Hashing;

use FQL\Enum\Type;
use FQL\Functions\Core\ScalarFunction;

final class Md5 implements ScalarFunction
{
    public static function name(): string
    {
        return 'MD5';
    }

    public static function execute(mixed $value): string
    {
        if ($value === null) {
            $value = '';
        }
        if (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }

        return md5($value);
    }
}
