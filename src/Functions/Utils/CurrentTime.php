<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class CurrentTime implements ScalarFunction
{
    public static function name(): string
    {
        return 'CURTIME';
    }

    public static function execute(bool $numeric = false): int|string
    {
        $now = new \DateTime();
        return $numeric
            ? (int) $now->format('His')
            : $now->format('H:i:s');
    }
}
