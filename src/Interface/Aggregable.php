<?php

namespace FQL\Interface;

interface Aggregable
{
    /**
     * Sum of the numeric values of `$key`; 0 for an empty result.
     */
    public function sum(string $key): float;

    /**
     * Average rounded to `$decimalPlaces`; null for an empty result or when no value
     * of `$key` is numeric.
     */
    public function avg(string $key, int $decimalPlaces = 2): ?float;

    /**
     * Smallest numeric value of `$key`; null for an empty result or when no value is
     * numeric.
     */
    public function min(string $key): ?float;

    /**
     * Largest numeric value of `$key`; null for an empty result or when no value is
     * numeric.
     */
    public function max(string $key): ?float;
}
