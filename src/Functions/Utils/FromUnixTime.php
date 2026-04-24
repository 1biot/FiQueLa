<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class FromUnixTime implements ScalarFunction
{
    public static function name(): string
    {
        return 'FROM_UNIXTIME';
    }

    public static function execute(mixed $timestamp, string $format = 'c'): ?string
    {
        if (!is_numeric($timestamp)) {
            return null;
        }

        $dateTime = (new \DateTimeImmutable())->setTimestamp((int) $timestamp);
        return $dateTime->format($format);
    }
}
