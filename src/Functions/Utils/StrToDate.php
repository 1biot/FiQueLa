<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class StrToDate implements ScalarFunction
{
    public static function name(): string
    {
        return 'STR_TO_DATE';
    }

    private const FORMAT_MAP = [
        'a' => 'D',
        'b' => 'M',
        'c' => 'n',
        'D' => 'jS',
        'd' => 'd',
        'e' => 'j',
        'f' => 'u',
        'H' => 'H',
        'h' => 'h',
        'I' => 'h',
        'i' => 'i',
        'j' => 'z',
        'k' => 'G',
        'l' => 'g',
        'M' => 'F',
        'm' => 'm',
        'p' => 'A',
        'r' => 'h:i:s A',
        'S' => 's',
        's' => 's',
        'T' => 'H:i:s',
        'U' => 'W',
        'u' => 'W',
        'V' => 'W',
        'v' => 'W',
        'W' => 'l',
        'w' => 'w',
        'X' => 'o',
        'x' => 'o',
        'Y' => 'Y',
        'y' => 'y',
        '%' => '%',
    ];

    public static function execute(mixed $value, string $format): ?string
    {
        if (!is_string($value) || $value === '' || $format === '') {
            return null;
        }

        if (!self::hasSupportedParts($format)) {
            return null;
        }

        if (self::hasWeekParts($format) && !self::hasRequiredWeekParts($format)) {
            return null;
        }

        if (self::hasDateParts($format) && !self::hasFullDateParts($format)) {
            return null;
        }

        if (self::hasTimeParts($format) && !self::hasRequiredTimeParts($format)) {
            return null;
        }

        $phpFormat = self::convertFormat($format);
        $date = \DateTimeImmutable::createFromFormat('!' . $phpFormat, $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (!$date instanceof \DateTimeImmutable) {
            $trailingPosition = $errors === false ? null : self::getTrailingDataPosition($errors);
            if ($trailingPosition !== null) {
                $trimmedValue = substr($value, 0, $trailingPosition);
                $date = \DateTimeImmutable::createFromFormat('!' . $phpFormat, $trimmedValue);
                $errors = \DateTimeImmutable::getLastErrors();
            }
        }

        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }

        if ($errors !== false && !self::isParseValid($errors)) {
            return null;
        }

        return self::formatResult($date, $format);
    }

    private static function convertFormat(string $format): string
    {
        $converted = '';
        $length = strlen($format);
        for ($i = 0; $i < $length; $i++) {
            $char = $format[$i];
            if ($char === '%' && isset($format[$i + 1])) {
                $specifier = $format[$i + 1];
                $converted .= self::FORMAT_MAP[$specifier] ?? self::escapeLiteral($specifier);
                $i++;
                continue;
            }

            $converted .= self::escapeLiteral($char);
        }

        return $converted;
    }

    private static function escapeLiteral(string $char): string
    {
        if (preg_match('/[a-zA-Z]/', $char) === 1) {
            return '\\' . $char;
        }

        return $char;
    }

    /**
     * @param array{warning_count:int,warnings:array<int,string>,error_count:int,errors:array<int,string>} $errors
     */
    private static function isParseValid(array $errors): bool
    {
        if ($errors['error_count'] > 0 && !self::isOnlyTrailingData($errors['errors'])) {
            return false;
        }

        if ($errors['warning_count'] > 0 && !self::isOnlyTrailingData($errors['warnings'])) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, string> $items
     */
    private static function isOnlyTrailingData(array $items): bool
    {
        foreach ($items as $message) {
            if (mb_strtolower($message) !== 'trailing data') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{warning_count:int,warnings:array<int,string>,error_count:int,errors:array<int,string>} $errors
     */
    private static function getTrailingDataPosition(array $errors): ?int
    {
        $positions = [];
        foreach ([$errors['errors'], $errors['warnings']] as $items) {
            foreach ($items as $position => $message) {
                if (mb_strtolower($message) === 'trailing data') {
                    $positions[] = (int) $position;
                }
            }
        }

        return $positions === [] ? null : min($positions);
    }

    private static function formatResult(\DateTimeImmutable $date, string $format): ?string
    {
        $hasDateParts = self::hasDateParts($format);
        $hasTimeParts = self::hasTimeParts($format);
        $hasMicroseconds = str_contains($format, '%f');

        if ($hasDateParts && $hasTimeParts) {
            return $date->format($hasMicroseconds ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s');
        }

        if ($hasDateParts) {
            return $date->format('Y-m-d');
        }

        if ($hasTimeParts) {
            return $date->format($hasMicroseconds ? 'H:i:s.u' : 'H:i:s');
        }

        return null;
    }

    private static function hasSupportedParts(string $format): bool
    {
        $specifiers = self::collectSpecifiers($format);
        foreach ($specifiers as $specifier) {
            if (!array_key_exists($specifier, self::FORMAT_MAP)) {
                return false;
            }
        }

        return $specifiers !== [];
    }

    private static function hasDateParts(string $format): bool
    {
        return self::containsAny($format, [
            '%Y', '%y', '%c', '%m', '%M', '%b', '%d', '%e', '%D', '%j',
            '%U', '%u', '%V', '%v', '%X', '%x', '%W', '%w',
        ]);
    }

    private static function hasFullDateParts(string $format): bool
    {
        if (self::hasWeekParts($format)) {
            return true;
        }

        $hasYear = self::containsAny($format, ['%Y', '%y']);
        $hasMonth = self::containsAny($format, ['%c', '%m', '%M', '%b']);
        $hasDay = self::containsAny($format, ['%d', '%e', '%D', '%j']);

        return $hasYear && $hasMonth && $hasDay;
    }

    private static function hasTimeParts(string $format): bool
    {
        return self::containsAny($format, ['%H', '%h', '%I', '%k', '%l', '%i', '%s', '%S', '%f', '%r', '%T', '%p']);
    }

    private static function hasRequiredTimeParts(string $format): bool
    {
        $hasHour = self::containsAny($format, ['%H', '%h', '%I', '%k', '%l', '%r', '%T']);
        $hasMinute = self::containsAny($format, ['%i', '%r', '%T']);

        return $hasHour && $hasMinute;
    }

    private static function hasWeekParts(string $format): bool
    {
        return self::containsAny($format, ['%U', '%u', '%V', '%v', '%X', '%x']);
    }

    private static function hasRequiredWeekParts(string $format): bool
    {
        $hasWeekYear = self::containsAny($format, ['%X', '%x', '%Y', '%y']);
        $hasWeek = self::containsAny($format, ['%U', '%u', '%V', '%v']);
        $hasWeekday = self::containsAny($format, ['%W', '%w']);

        return $hasWeekYear && $hasWeek && $hasWeekday;
    }

    /**
     * @return array<int, string>
     */
    private static function collectSpecifiers(string $format): array
    {
        $specifiers = [];
        $length = strlen($format);
        for ($i = 0; $i < $length; $i++) {
            if ($format[$i] === '%' && isset($format[$i + 1])) {
                $specifiers[] = $format[$i + 1];
                $i++;
            }
        }

        return $specifiers;
    }

    /**
     * @param array<int, string> $tokens
     */
    private static function containsAny(string $format, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (str_contains($format, $token)) {
                return true;
            }
        }

        return false;
    }
}
