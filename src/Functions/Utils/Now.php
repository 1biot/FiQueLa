<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class Now implements ScalarFunction
{
    public static function name(): string
    {
        return 'NOW';
    }

    public static function execute(bool $numeric = false): int|string
    {
        $today = new \DateTime();
        return $numeric
            ? (int) $today->format('YmdHis')
            : $today->format('Y-m-d H:i:s');
    }
}
