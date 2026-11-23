<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;

/**
 * Value resolution shared by MIN and MAX.
 *
 * A value is either a **number** (int, float or numeric string) or a **date**
 * (`DateTimeInterface`, or a string that parses as an absolute date and/or
 * time). Numbers compare numerically and dates chronologically; the two kinds
 * never compare with each other. The original value is kept, so a date
 * string comes back in the format it was read in.
 *
 * @internal
 * @phpstan-type Resolved array{
 *     kind: 'number'|'date',
 *     key: int|float|\DateTimeImmutable,
 *     value: mixed
 * }
 */
final class ComparableValue
{
    private const RELATIVE_OFFSETS = ['year', 'month', 'day', 'hour', 'minute', 'second'];

    /**
     * Returns null for a value the aggregate skips: `null` and empty or
     * whitespace-only strings (an empty cell in a CSV / XLSX column).
     *
     * @return Resolved|null
     * @throws UnexpectedValueException
     */
    public static function resolve(mixed $value, string $function): ?array
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return [
                'kind' => 'date',
                'key' => \DateTimeImmutable::createFromInterface($value),
                'value' => $value,
            ];
        }

        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if (is_numeric($value)) {
            $numeric = $value + 0;
            return ['kind' => 'number', 'key' => $numeric, 'value' => $numeric];
        }

        $date = is_string($value) ? self::parseDate($value) : null;
        if ($date === null) {
            throw new UnexpectedValueException(
                sprintf('%s value is neither numeric nor a date: %s', $function, var_export($value, true))
            );
        }

        return ['kind' => 'date', 'key' => $date, 'value' => $value];
    }

    /**
     * Returns a negative number, zero or a positive number when `$a` is
     * smaller than, equal to or greater than `$b`.
     *
     * @param Resolved $a
     * @param Resolved $b
     * @throws UnexpectedValueException when a date meets a number
     */
    public static function compare(array $a, array $b, string $function): int
    {
        if ($a['kind'] !== $b['kind']) {
            throw new UnexpectedValueException(
                sprintf(
                    '%s cannot compare a date with a number: %s and %s',
                    $function,
                    var_export($a['value'], true),
                    var_export($b['value'], true)
                )
            );
        }

        return $a['key'] <=> $b['key'];
    }

    /**
     * Parses an absolute date, datetime or time. Strings without a digit
     * ("today", "monday") and relative expressions ("+1 day", "tomorrow")
     * are rejected, so a word in a numeric column still fails loudly instead
     * of resolving against the current clock. A time without a date is
     * placed on 1970-01-01, which keeps times comparable with each other.
     */
    private static function parseDate(string $value): ?\DateTimeImmutable
    {
        if (preg_match('/\d/', $value) !== 1) {
            return null;
        }

        $parsed = date_parse($value);
        if ($parsed['error_count'] > 0 || $parsed['warning_count'] > 0) {
            return null;
        }

        $hasDate = $parsed['year'] !== false && $parsed['month'] !== false && $parsed['day'] !== false;
        $hasTime = $parsed['hour'] !== false && $parsed['minute'] !== false;
        if (!$hasDate && !$hasTime) {
            return null;
        }

        if (isset($parsed['relative']) && !($hasDate && self::isWeekdayOnly($parsed['relative']))) {
            return null;
        }

        try {
            return new \DateTimeImmutable($hasDate ? $value : '1970-01-01 ' . $value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * RFC 2822 dates ("Sun, 16 Aug 2026 10:15:51 GMT") carry their weekday as
     * a relative part with zero offsets. That is still an absolute date.
     *
     * @param array<string, mixed> $relative
     */
    private static function isWeekdayOnly(array $relative): bool
    {
        foreach ($relative as $part => $amount) {
            if ($part === 'weekday') {
                continue;
            }
            if (!in_array($part, self::RELATIVE_OFFSETS, true) || $amount !== 0) {
                return false;
            }
        }

        return true;
    }
}
