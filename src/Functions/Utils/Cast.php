<?php

namespace FQL\Functions\Utils;

use FQL\Enum\Type;
use FQL\Functions\Core\ScalarFunction;

final class Cast implements ScalarFunction
{
    public static function name(): string
    {
        return 'CAST';
    }

    public static function execute(mixed $value, Type $targetType): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if ($targetType === Type::NUMBER) {
            return is_numeric($value) ? $value : 0;
        }

        return Type::castValue(
            $value,
            $targetType === Type::TRUE || $targetType === Type::FALSE ? Type::BOOLEAN : $targetType
        );
    }
}
