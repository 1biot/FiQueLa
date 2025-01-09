<?php

namespace UQL\Traits\Helpers;

use UQL\Exceptions\InvalidArgumentException;

trait NestedArrayAccessor
{
    /**
     * Accesses a nested value from an array based on a dot-separated path.
     * Supports advanced syntax for iterating over arrays and extracting specific keys:
     * - Standard dot notation: `key.subkey`
     * - Array index access: `key.subkey.0`
     * - Array iteration with key extraction: `key.subkey[]->subkey`
     * - Array index with key extraction: `key.subkey.0->subkey`
     *
     * @param array<string|int, mixed> $data The array to retrieve the value from.
     * @param string $field The dot-separated path to the desired value.
     *                      Examples:
     *                      - `a.b.c` retrieves `['a']['b']['c']`.
     *                      - `a.e[]->z` retrieves all `z` keys from array `['a']['e']`.
     *                      - `a.e.0->z` retrieves the `z` key from the first element of array `['a']['e']`.
     * @param bool $throwOnMissing If true, throws an exception when the path does not exist.
     *                              Defaults to false, returning null for invalid paths.
     * @return mixed The value at the specified path, or null if the path does not exist and $throwOnMissing is false.
     * @throws InvalidArgumentException If a required key or index is missing and $throwOnMissing is true.
     */
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
