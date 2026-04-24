<?php

namespace FQL\Functions\String;

use FQL\Enum\Type;
use FQL\Functions\Core\ScalarFunction;

final class Reverse implements ScalarFunction
{
    public static function name(): string
    {
        return 'REVERSE';
    }

    public static function execute(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }
        return self::mbStrRev($value);
    }

    private static function mbStrRev(string $string): string
    {
        $r = '';
        for ($i = mb_strlen($string); $i >= 0; $i--) {
            $r .= mb_substr($string, $i, 1);
        }
        return $r;
    }
}
