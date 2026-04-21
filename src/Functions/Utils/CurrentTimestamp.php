<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class CurrentTimestamp implements ScalarFunction
{
    public static function name(): string
    {
        return 'CURRENT_TIMESTAMP';
    }

    public static function execute(): int
    {
        return time();
    }
}
