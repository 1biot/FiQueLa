<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class DateAdd implements ScalarFunction
{
    public static function name(): string
    {
        return 'DATE_ADD';
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

        try {
            $d = new \DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }

        if (class_exists(\DateMalformedStringException::class)) {
            try {
                $modified = @$d->modify($interval);
            /** @phpstan-ignore-next-line */
            } catch (\DateMalformedStringException) {
                return null;
            }
        } else {
            $modified = @$d->modify($interval);
        }

        if (!$modified instanceof \DateTimeImmutable) {
            return null;
        }

        return $modified->format('Y-m-d H:i:s');
    }
}
