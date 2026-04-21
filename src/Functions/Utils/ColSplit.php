<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\ScalarFunction;

final class ColSplit implements ScalarFunction
{
    public static function name(): string
    {
        return 'COL_SPLIT';
    }

    /**
     * @return array<string, mixed>|null Map of [columnName => entry], or null if input is not an array.
     */
    public static function execute(mixed $value, ?string $format = null, ?string $keyField = null, string $baseFieldName = ''): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $baseName = self::sanitizeFieldNameStatic($baseFieldName);
        $effectiveFormat = $format ?? "{$baseName}_%index";

        $result = [];
        foreach (array_values($value) as $i => $entry) {
            $suffix = self::getSuffixFromEntryStatic($entry, $i, $keyField);
            $colName = str_replace('%index', (string) $suffix, $effectiveFormat);
            $result[$colName] = $entry;
        }

        return $result;
    }

    private static function getSuffixFromEntryStatic(mixed $entry, int $index, ?string $keyField): string|int
    {
        if ($keyField !== null && $keyField !== '' && is_array($entry) && array_key_exists($keyField, $entry)) {
            return $entry[$keyField];
        }

        return $index + 1;
    }

    private static function sanitizeFieldNameStatic(string $field): string
    {
        return str_replace(['.', '[', ']'], '_', $field);
    }
}
