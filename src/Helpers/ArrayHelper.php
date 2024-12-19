<?php

namespace JQL\Helpers;

use JQL\Exceptions\InvalidArgumentException;

class ArrayHelper
{
    /**
     * Získá hodnotu z víceúrovňového pole nebo objektu na základě klíče s tečkami.
     *
     * @param array<string|int, mixed> $data
     * @param string $key
     * @return mixed
     */
    public static function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $current = $data;

        foreach ($keys as $k) {
            if (is_array($current) && array_key_exists($k, $current)) {
                $current = $current[$k];
            } else {
                throw new InvalidArgumentException(sprintf('Field "%s" not found', $k));
            }
        }

        return $current;
    }
}
