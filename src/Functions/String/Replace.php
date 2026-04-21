<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\ScalarFunction;

final class Replace implements ScalarFunction
{
    public static function name(): string
    {
        return 'REPLACE';
    }

    public static function execute(mixed $value, mixed $search, mixed $replace): mixed
    {
        $search = (string) $search;
        $replace = (string) $replace;

        if (is_array($value)) {
            $returnValue = [];
            foreach ($value as $v) {
                if (is_scalar($v)) {
                    $returnValue[] = str_replace($search, $replace, (string) $v);
                } else {
                    $returnValue[] = null;
                }
            }
            return $returnValue;
        } elseif (!is_scalar($value)) {
            return null;
        }

        return str_replace($search, $replace, (string) $value);
    }
}
