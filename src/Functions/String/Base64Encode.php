<?php

namespace FQL\Functions\String;

use FQL\Enum\Type;
use FQL\Exception\InvalidArgumentException;
use FQL\Functions\Core\ScalarFunction;

final class Base64Encode implements ScalarFunction
{
    public static function name(): string
    {
        return 'BASE64_ENCODE';
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function execute(mixed $value): string
    {
        return base64_encode(Type::castValue($value, Type::STRING));
    }
}
