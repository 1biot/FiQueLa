<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class Day implements ScalarFunction
{
    public static function name(): string
    {
        return 'DAY';
    }

    public static function execute(mixed $date): ?int
    {
        $dt = self::parseDateValue($date);
        if ($dt === null) {
            return null;
        }

        return (int) $dt->format('j');
    }

    private static function parseDateValue(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value)) {
            return (new \DateTimeImmutable())->setTimestamp($value);
        }

        if (is_string($value) && strtotime($value) !== false) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
