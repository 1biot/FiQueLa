<?php

namespace FQL\Functions\Core;

/**
 * Contract for FQL aggregate functions (N rows in → one value out).
 *
 * Implementations are **pure static utility classes** — the grouping phase
 * holds the accumulator state externally. Evaluation unfolds as:
 *
 * ```php
 * $acc = Sum::initial(['distinct' => false]);     // empty accumulator
 * foreach ($rowsInGroup as $row) {
 *     $value = $evaluator->evaluate($expression, $row);
 *     $acc   = Sum::accumulate($acc, $value);      // incremental
 * }
 * $result = Sum::finalize($acc);                   // final scalar
 * ```
 *
 * The accumulator shape is opaque to the engine (`mixed`); each aggregate
 * chooses what it needs (scalar for SUM, array for GROUP_CONCAT, etc.).
 *
 * `initial()` accepts an `$options` array so each aggregate can read
 * implementation-specific flags without forcing a bespoke signature:
 *  - `distinct` (bool) — enforce value uniqueness inside the group.
 *  - `separator` (string) — used by GROUP_CONCAT.
 *
 * Unknown options are ignored. Aggregate implementations should stash every
 * option they care about inside the accumulator so they remain accessible
 * during {@see accumulate()}.
 */
interface AggregateFunction
{
    /**
     * Upper-cased FQL identifier (`SUM`, `AVG`, `COUNT`, …).
     */
    public static function name(): string;

    /**
     * Empty accumulator for a new group.
     *
     * @param array<string, mixed> $options aggregate-specific config
     *        (e.g. `['distinct' => true, 'separator' => ', ']`)
     */
    public static function initial(array $options = []): mixed;

    /**
     * Returns the updated accumulator after consuming one row's value.
     * `null` values follow SQL conventions (typically skipped).
     */
    public static function accumulate(mixed $acc, mixed $value): mixed;

    /**
     * Produces the final scalar result from the accumulator.
     */
    public static function finalize(mixed $acc): mixed;
}
