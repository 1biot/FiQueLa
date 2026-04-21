<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class DateFormat implements ScalarFunction
{
    public static function name(): string
    {
        return 'DATE_FORMAT';
    }

    public static function execute(mixed $date, string $format = 'c'): ?string
    {
        if (is_string($date) && strtotime($date) !== false) {
            try {
                $date = new \DateTimeImmutable($date);
            } catch (\Exception) {
                $date = null;
            }
        }

        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }

        return $date->format($format);
    }
}
