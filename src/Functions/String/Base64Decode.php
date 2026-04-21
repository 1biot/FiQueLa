<?php

namespace FQL\Functions\String;

use FQL\Enum\Type;
use FQL\Exception\InvalidArgumentException;
use FQL\Functions\Core\ScalarFunction;

final class Base64Decode implements ScalarFunction
{
    public static function name(): string
    {
        return 'BASE64_DECODE';
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function execute(mixed $value): string
    {
        return (string) base64_decode(Type::castValue($value, Type::STRING));
    }
}
