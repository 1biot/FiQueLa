<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class DateDiff implements ScalarFunction
{
    public static function name(): string
    {
        return 'DATE_DIFF';
    }

    public static function execute(mixed $date1, mixed $date2): ?int
    {
        if (
            !is_string($date1)
            || !is_string($date2)
            || strtotime($date1) === false
            || strtotime($date2) === false
        ) {
            return null;
        }

        try {
            $d1 = new \DateTime($date1);
            $d2 = new \DateTime($date2);
            return (int) $d1->diff($d2)->format('%r%a');
        } catch (\Exception) {
            return null;
        }
    }
}
