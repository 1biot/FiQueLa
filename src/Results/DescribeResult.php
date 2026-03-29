<?php

namespace FQL\Results;

use FQL\Enum\Type;
use FQL\Stream\ArrayStreamProvider;
use FQL\Traits\Helpers\EnhancedNestedArrayAccessor;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
class DescribeResult extends ResultsProvider
{
    use EnhancedNestedArrayAccessor;

    private const EMPTY_TYPES = ['null', 'empty-string', 'whitespace'];

    private int $sourceRowCount = 0;

    public function __construct(
        private readonly Stream $source
    ) {
    }

    public function getSourceRowCount(): int
    {
        return $this->sourceRowCount;
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function getIterator(): \Generator
    {
        $counter = 0;
        /** @var array<string, array<string, int>> $arrayKeys */
        $arrayKeys = [];
        /** @var array<string, array<string, int>|null> $colEnumCheck */
        $colEnumCheck = [];
        /** @var array<string, array<string, true>|null> $uniqueTrackers */
        $uniqueTrackers = [];

        /** @var array<string, list<string>> $colPaths */
        $colPaths = [];

        foreach ($this->source->getIterator() as $item) {
            $counter++;
            /** @var array<string, array{value: mixed, path: list<string>}> $flattenedItem */
            $flattenedItem = $this->flattenItem($item);

            // track missing fields
            foreach (array_keys($arrayKeys) as $knownKey) {
                if (!array_key_exists($knownKey, $flattenedItem)) {
                    $arrayKeys[$knownKey]['null'] = ($arrayKeys[$knownKey]['null'] ?? 0) + 1;
                }
            }

            foreach ($flattenedItem as $key => $entry) {
                if (!is_string($key)) {
                    continue;
                }

                $value = $entry['value'];
                $typeName = $this->resolveTypeName($value);

                if (!isset($arrayKeys[$key])) {
                    $arrayKeys[$key] = [];
                    $colPaths[$key] = $entry['path'];
                }
                $arrayKeys[$key][$typeName] = ($arrayKeys[$key][$typeName] ?? 0) + 1;

                $this->trackEnum($colEnumCheck, $key, $value);
                $this->trackUnique($uniqueTrackers, $key, $value, $typeName);
            }
        }

        $this->sourceRowCount = $counter;

        foreach (array_keys($arrayKeys) as $key) {
            yield $this->buildColumnRow(
                $key,
                $colPaths[$key] ?? [$key],
                $arrayKeys[$key],
                $colEnumCheck,
                $uniqueTrackers,
                $counter
            );
        }
    }

    private function resolveTypeName(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value)) {
            if ($value === '') {
                return 'empty-string';
            }
            if (trim($value) === '') {
                return 'whitespace';
            }
            if (in_array(strtolower($value), ['yes', 'no'], true)) {
                return 'bool-string';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/', $value)) {
                return 'date-string';
            }
        }

        return Type::match($value)->value;
    }

    /**
     * @param array<string, array<string, int>|null> $colEnumCheck
     */
    private function trackEnum(array &$colEnumCheck, string $key, mixed $value): void
    {
        if (!array_key_exists($key, $colEnumCheck)) {
            $colEnumCheck[$key] = [];
        }

        if ($colEnumCheck[$key] === null) {
            return;
        }

        if (!is_scalar($value)) {
            $colEnumCheck[$key] = null;
            return;
        }

        $stringValue = (string) $value;
        if ($stringValue === '' || trim($stringValue) === '') {
            return;
        }

        $colEnumCheck[$key][$stringValue] = ($colEnumCheck[$key][$stringValue] ?? 0) + 1;
        if (count($colEnumCheck[$key]) > 5) {
            $colEnumCheck[$key] = null;
        }
    }

    /**
     * @param array<string, array<string, true>|null> $uniqueTrackers
     */
    private function trackUnique(array &$uniqueTrackers, string $key, mixed $value, string $typeName): void
    {
        if (!array_key_exists($key, $uniqueTrackers)) {
            $uniqueTrackers[$key] = [];
        }

        if ($uniqueTrackers[$key] === null) {
            return;
        }

        if (!is_scalar($value) || in_array($typeName, self::EMPTY_TYPES, true)) {
            return;
        }

        $stringValue = (string) $value;
        if (isset($uniqueTrackers[$key][$stringValue])) {
            $uniqueTrackers[$key] = null;
        } else {
            $uniqueTrackers[$key][$stringValue] = true;
        }
    }

    /**
     * @param list<string> $path
     * @param array<string, int> $types
     * @param array<string, array<string, int>|null> $colEnumCheck
     * @param array<string, array<string, true>|null> $uniqueTrackers
     * @return array<string, mixed>
     */
    private function buildColumnRow(
        string $key,
        array $path,
        array $types,
        array $colEnumCheck,
        array $uniqueTrackers,
        int $counter
    ): array {
        $total = array_sum($types);

        $filledCount = array_sum(array_filter(
            $types,
            fn(int $count, string $type) => !in_array($type, self::EMPTY_TYPES, true),
            ARRAY_FILTER_USE_BOTH
        ));

        arsort($types);
        $dominantType = $types !== [] ? array_key_first($types) : null;

        $allTypeKeys = array_keys($types);
        $emptyTypeKeys = array_filter($allTypeKeys, fn(string $t) => in_array($t, self::EMPTY_TYPES, true));
        $nonEmptyTypes = array_diff($allTypeKeys, $emptyTypeKeys);

        $isNumericCombo = in_array('int', $nonEmptyTypes, true)
            && in_array('double', $nonEmptyTypes, true)
            && count($nonEmptyTypes) === 2;

        $suspicious = count($emptyTypeKeys) > 1
            || (count($nonEmptyTypes) > 1 && !$isNumericCombo);

        $confidence = $total > 0 ? ($types[$dominantType] / $total) : 0.0;
        $missingCount = array_sum(array_intersect_key($types, array_flip(self::EMPTY_TYPES)));
        $completeness = $counter > 0 ? (($counter - $missingCount) / $counter) : 0.0;

        $enumSet = $colEnumCheck[$key] ?? null;
        $isEnum = false;
        $isConstant = false;

        if (is_array($enumSet)) {
            $uniqueCount = count($enumSet);
            $occurrenceCount = array_sum($enumSet);

            if ($uniqueCount === 1 && $occurrenceCount === $filledCount) {
                $isConstant = true;
            } elseif (
                $uniqueCount >= 2
                && $uniqueCount <= 5
                && $occurrenceCount === $filledCount
                && $completeness > 0.1
            ) {
                $isEnum = true;
            }
        }

        $isUnique = isset($uniqueTrackers[$key])
            && count($uniqueTrackers[$key]) > 0
            && $filledCount > 0
            && count($uniqueTrackers[$key]) === $filledCount;

        return [
            'column' => $key,
            'path' => $path,
            'types' => $types,
            'totalRows' => $filledCount,
            'totalTypes' => count($types),
            'dominant' => $dominantType,
            'suspicious' => $suspicious,
            'confidence' => round($confidence, 4),
            'completeness' => round($completeness, 4),
            'constant' => $isConstant,
            'isEnum' => $isEnum,
            'isUnique' => $isUnique,
        ];
    }

    /**
     * Recursively flattens nested associative arrays into dot notation keys (up to 3 levels deep)
     *
     * @param StreamProviderArrayIteratorValue|\Generator<StreamProviderArrayIteratorValue>|\Traversable<StreamProviderArrayIteratorValue> $item
     * @param list<string> $parentPath
     * @return array<string, array{value: mixed, path: list<string>}>
     */
    private function flattenItem(
        array|\Generator|\Traversable $item,
        string $prefix = '',
        int $level = 0,
        array $parentPath = []
    ): array {
        if ($level >= 3) {
            return [];
        }

        $flat = [];
        foreach ($item as $key => $value) {
            $dottedKey = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;
            $currentPath = [...$parentPath, (string) $key];
            if (is_array($value) && $this->isAssoc($value)) {
                $flat += $this->flattenItem($value, $dottedKey, $level + 1, $currentPath);
            } else {
                $flat[$dottedKey] = ['value' => $value, 'path' => $currentPath];
            }
        }

        return $flat;
    }
}
