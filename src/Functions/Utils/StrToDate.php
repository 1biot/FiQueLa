<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\MultipleFieldsFunction;

class StrToDate extends MultipleFieldsFunction
{
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

    public function __construct(private readonly string $valueField, private readonly string $format)
    {
        parent::__construct($valueField, $format);
    }

    public function __invoke(array $item, array $resultItem): ?string
    {
        $value = $this->getFieldValue($this->valueField, $item, $resultItem) ?? $this->valueField;
        $format = $this->getFieldValue($this->format, $item, $resultItem) ?? $this->format;

        if (!is_string($value) || !is_string($format) || $value === '' || $format === '') {
            return null;
        }

        if (!$this->hasSupportedParts($format)) {
            return null;
        }

        if ($this->hasWeekParts($format) && !$this->hasRequiredWeekParts($format)) {
            return null;
        }

        if ($this->hasDateParts($format) && !$this->hasFullDateParts($format)) {
            return null;
        }

        if ($this->hasTimeParts($format) && !$this->hasRequiredTimeParts($format)) {
            return null;
        }

        $phpFormat = $this->convertFormat($format);
        $date = \DateTimeImmutable::createFromFormat('!' . $phpFormat, $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (!$date instanceof \DateTimeImmutable) {
            $trailingPosition = $errors === false ? null : $this->getTrailingDataPosition($errors);
            if ($trailingPosition !== null) {
                $trimmedValue = substr($value, 0, $trailingPosition);
                $date = \DateTimeImmutable::createFromFormat('!' . $phpFormat, $trimmedValue);
                $errors = \DateTimeImmutable::getLastErrors();
            }
        }

        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }

        if ($errors !== false && !$this->isParseValid($errors)) {
            return null;
        }

        return $this->formatResult($date, $format);
    }

    private function convertFormat(string $format): string
    {
        $converted = '';
        $length = strlen($format);
        for ($i = 0; $i < $length; $i++) {
            $char = $format[$i];
            if ($char === '%' && isset($format[$i + 1])) {
                $specifier = $format[$i + 1];
                $converted .= self::FORMAT_MAP[$specifier] ?? $this->escapeLiteral($specifier);
                $i++;
                continue;
            }

            $converted .= $this->escapeLiteral($char);
        }

        return $converted;
    }

    private function escapeLiteral(string $char): string
    {
        if (preg_match('/[a-zA-Z]/', $char) === 1) {
            return '\\' . $char;
        }

        return $char;
    }

    /**
     * @param array{warning_count:int,warnings:array<int,string>,error_count:int,errors:array<int,string>} $errors
     */
    private function isParseValid(array $errors): bool
    {
        if ($errors['error_count'] > 0 && !$this->isOnlyTrailingData($errors['errors'])) {
            return false;
        }

        if ($errors['warning_count'] > 0 && !$this->isOnlyTrailingData($errors['warnings'])) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, string> $items
     */
    private function isOnlyTrailingData(array $items): bool
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
    private function getTrailingDataPosition(array $errors): ?int
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

    private function formatResult(\DateTimeImmutable $date, string $format): ?string
    {
        $hasDateParts = $this->hasDateParts($format);
        $hasTimeParts = $this->hasTimeParts($format);
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

    private function hasSupportedParts(string $format): bool
    {
        $specifiers = $this->collectSpecifiers($format);
        foreach ($specifiers as $specifier) {
            if (!array_key_exists($specifier, self::FORMAT_MAP)) {
                return false;
            }
        }

        return $specifiers !== [];
    }

    private function hasDateParts(string $format): bool
    {
        return $this->containsAny($format, ['%Y', '%y', '%c', '%m', '%M', '%b', '%d', '%e', '%D', '%j', '%U', '%u', '%V', '%v', '%X', '%x', '%W', '%w']);
    }

    private function hasFullDateParts(string $format): bool
    {
        if ($this->hasWeekParts($format)) {
            return true;
        }

        $hasYear = $this->containsAny($format, ['%Y', '%y']);
        $hasMonth = $this->containsAny($format, ['%c', '%m', '%M', '%b']);
        $hasDay = $this->containsAny($format, ['%d', '%e', '%D', '%j']);

        return $hasYear && $hasMonth && $hasDay;
    }

    private function hasTimeParts(string $format): bool
    {
        return $this->containsAny($format, ['%H', '%h', '%I', '%k', '%l', '%i', '%s', '%S', '%f', '%r', '%T', '%p']);
    }

    private function hasRequiredTimeParts(string $format): bool
    {
        $hasHour = $this->containsAny($format, ['%H', '%h', '%I', '%k', '%l', '%r', '%T']);
        $hasMinute = $this->containsAny($format, ['%i', '%r', '%T']);

        return $hasHour && $hasMinute;
    }

    private function hasWeekParts(string $format): bool
    {
        return $this->containsAny($format, ['%U', '%u', '%V', '%v', '%X', '%x']);
    }

    private function hasRequiredWeekParts(string $format): bool
    {
        $hasWeekYear = $this->containsAny($format, ['%X', '%x', '%Y', '%y']);
        $hasWeek = $this->containsAny($format, ['%U', '%u', '%V', '%v']);
        $hasWeekday = $this->containsAny($format, ['%W', '%w']);

        return $hasWeekYear && $hasWeek && $hasWeekday;
    }

    /**
     * @return array<int, string>
     */
    private function collectSpecifiers(string $format): array
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
    private function containsAny(string $format, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (str_contains($format, $token)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, "%s")',
            $this->getName(),
            $this->valueField,
            $this->format
        );
    }
}
