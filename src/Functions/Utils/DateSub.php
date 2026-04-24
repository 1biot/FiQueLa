<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class DateSub implements ScalarFunction
{
    public static function name(): string
    {
        return 'DATE_SUB';
    }

    public static function execute(mixed $date, string $interval): ?string
    {
        if (!is_string($date) || strtotime($date) === false) {
            return null;
        }

        $interval = trim($interval);
        if ($interval === '') {
            return null;
        }

        if (str_starts_with($interval, '+')) {
            $interval = '-' . ltrim(substr($interval, 1));
        } elseif (!str_starts_with($interval, '-')) {
            $interval = '-' . $interval;
        }

        try {
            $d = new \DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }

        $modified = $d->modify($interval);
        if (!$modified instanceof \DateTimeImmutable) {
            return null;
        }

        return $modified->format('Y-m-d H:i:s');
    }
}
