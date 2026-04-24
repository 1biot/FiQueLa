<?php

namespace FQL\Functions\Hashing;

use FQL\Enum\Type;
use FQL\Functions\Core\ScalarFunction;

final class Sha1 implements ScalarFunction
{
    public static function name(): string
    {
        return 'SHA1';
    }

    public static function execute(mixed $value): string
    {
        if ($value === null) {
            $value = '';
        }
        if (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }

        return sha1($value);
    }
}
