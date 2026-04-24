<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class CurrentDate implements ScalarFunction
{
    public static function name(): string
    {
        return 'CURDATE';
    }

    public static function execute(bool $numeric = false): int|string
    {
        $today = new \DateTime();
        return $numeric
            ? (int) $today->format('Ymd')
            : $today->format('Y-m-d');
    }
}
