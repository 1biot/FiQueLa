<?php

namespace FQL\Traits\Helpers;

use FQL\Exception\InvalidArgumentException;

trait EnhancedNestedArrayAccessor
{
    /**
     * Access a nested value using dot notation, `escaped.keys`, array iteration with [] and wildcard *.
     *
     * Supports:
     * - Standard access: a.b.c
     * - Indexed access: a.b.0.c
     * - Iterated access: a.b[].c.d
     * - Wildcard access: a.b.* (expands all keys of associative array)
     * - Escaped keys: `key.with.dot`, `key with space`
     *   > Note: keys with spaces work also without backticks
     * - Indexed access into scalar via [index] if scalar is wrapped (e.g., x[0])
     *
     * @param array<string|int, mixed> $data
     * @param string $field
     * @param bool $throwOnMissing
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function accessNestedValue(array $data, string $field, bool $throwOnMissing = true): mixed
    {
        return $this->resolvePath($data, $this->parsePath($field), $throwOnMissing);
    }

    /**
     * Removes a nested value based on the path.
     *
     * @param array<string|int, mixed> $data
     * @param string $field
     */
    public function removeNestedValue(array &$data, string $field): void
    {
        $tokens = $this->parsePath($field);
        if (empty($tokens)) {
            return;
        }

        $last = array_pop($tokens);
        $ref = &$this->resolveReference($data, $tokens);

        if (is_array($ref) && array_key_exists($last['key'], $ref)) {
            unset($ref[$last['key']]);
        }
    }

    /**
     * Tokenizes path with support for escaped keys (`key.with.dot`), iteration ([]) and wildcard (*)
     * Also supports keys with spaces without backticks.
     * @param string $path
     * @return array<int, array{key: string, iterate: bool, wildcard: bool}>
     */
    private function parsePath(string $path): array
    {
        $pattern = '/`([^`]+)`|([^.`\[\]]+)|(\[])/';
        preg_match_all($pattern, $path, $matches);

        $tokens = [];
        $parts = $matches[0];

        for ($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];

            if ($part === '[]') {
                if (empty($tokens)) {
                    throw new InvalidArgumentException("Invalid path: [] cannot be at the start");
                }
                $tokens[count($tokens) - 1]['iterate'] = true;
            } elseif ($part === '*') {
                $tokens[] = [
                    'key' => '*',
                    'iterate' => false,
                    'wildcard' => true,
                ];
            } else {
                $key = trim($part, '`');
                $tokens[] = [
                    'key' => $key,
                    'iterate' => false,
                    'wildcard' => false,
                ];
            }
        }

        return $tokens;
    }

    /**
     * Traverses array structure using parsed tokens.
     *
     * @param mixed $current
     * @param array<int, array{key: string, iterate: bool, wildcard: bool}> $tokens
     * @param bool $throwOnMissing
     * @return mixed
     */
    private function resolvePath(mixed $current, array $tokens, bool $throwOnMissing): mixed
    {
        if (empty($tokens)) {
            return null;
        }

        $token = array_shift($tokens);

        // Wildcard: expand all keys of current array
        if ($token['wildcard']) {
            if (!is_array($current)) {
                if ($throwOnMissing) {
                    throw new InvalidArgumentException("Expected array for wildcard *, got " . gettype($current));
                }
                return null;
            }

            if (empty($tokens)) {
                return $current;
            }

            $result = [];
            foreach ($current as $key => $value) {
                $resolved = $this->resolvePath($value, $tokens, $throwOnMissing);
                $result[$key] = $resolved;
            }
            return $result;
        }

        $key = $token['key'];

        if (!is_array($current)) {
            // Try to wrap scalars into an array for indexed access
            if (ctype_digit($key)) {
                $current = [$current];
            } else {
                if ($throwOnMissing) {
                    throw new InvalidArgumentException("Expected array, got " . gettype($current));
                }
                return null;
            }
        }

        if (!array_key_exists($key, $current)) {
            if ($throwOnMissing) {
                throw new InvalidArgumentException(sprintf('Field "%s" not found', $key));
            }
            return null;
        }

        $next = $current[$key];

        if ($token['iterate']) {
            if (!is_array($next)) {
                $next = [$next];
            } elseif ($this->isAssoc($next)) {
                $next = [$next];
            }

            return array_map(fn($item) => $tokens ? $this->resolvePath($item, $tokens, $throwOnMissing) : $item, $next);
        }

        return $tokens ? $this->resolvePath($next, $tokens, $throwOnMissing) : $next;
    }

    /**
     * Returns a reference to a nested value for modification.
     *
     * @param array<string|int, mixed> $data
     * @param array<int, array{key: string, iterate: bool, wildcard: bool}> $tokens
     * @return mixed
     */
    private function &resolveReference(array &$data, array $tokens): mixed
    {
        $ref = &$data;

        foreach ($tokens as $token) {
            if (!is_array($ref)) {
                $null = null;
                return $null;
            }

            if (!array_key_exists($token['key'], $ref)) {
                $ref[$token['key']] = [];
            }

            $ref = &$ref[$token['key']];
        }

        return $ref;
    }

    /**
     * Checks if the array is associative (has non-numeric keys).
     *
     * @param array<int|string, mixed> $array
     * @return bool
     */
    public function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
