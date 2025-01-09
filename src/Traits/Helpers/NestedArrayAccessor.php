<?php

namespace UQL\Traits\Helpers;

use UQL\Exceptions\InvalidArgumentException;

trait NestedArrayAccessor
{
    public function accessNestedValue(array $data, string $field, bool $throwOnMissing = true): mixed
    {
        // Special case: iteration or accessing an index with a subsequent key ->key
        if (preg_match('/^([\w.]+)\.(\d+)->(\w+)$/', $field, $matches)) {
            $arrayPath = $matches[1];
            $index = (int)$matches[2];
            $subKey = $matches[3];

            // Retrieving a specific index
            $nestedArray = $this->accessNestedValue($data, $arrayPath, $throwOnMissing);

            if (!is_array($nestedArray) || !isset($nestedArray[$index])) {
                if ($throwOnMissing) {
                    throw new InvalidArgumentException(
                        sprintf('Index "%d" not found in field "%s"', $index, $arrayPath)
                    );
                }
                return null;
            }

            // Returning a specific key from this index
            return $nestedArray[$index][$subKey]
                ?? ($throwOnMissing
                    ? throw new InvalidArgumentException(
                        sprintf('Key "%s" not found in index "%d" of field "%s"', $subKey, $index, $arrayPath)
                    )
                    : null);
        }

        // Standard case of iteration `[]->key`
        if (preg_match('/^([\w.]+)\[]->(\w+)$/', $field, $matches)) {
            $arrayPath = $matches[1];
            $subKey = $matches[2];

            $nestedArray = $this->accessNestedValue($data, $arrayPath, $throwOnMissing);

            if (!is_array($nestedArray)) {
                throw new InvalidArgumentException(sprintf('Field "%s" is not iterable or does not exist', $arrayPath));
            }

            return array_map(fn($item) => $item[$subKey] ?? null, $nestedArray);
        }

        // Standard traversal using dot notation
        $keys = explode('.', $field);
        $current = $data;

        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                if ($throwOnMissing) {
                    throw new InvalidArgumentException(sprintf('Field "%s" not found', $key));
                }
                return null;
            }
        }

        return $current;
    }
}
