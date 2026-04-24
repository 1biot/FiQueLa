<?php

namespace FQL\Functions\Core;

/**
 * Contract for scalar FQL functions (one row in → one value out).
 *
 * Implementations are **pure static utility classes** — no constructor, no
 * instance state, no `__invoke()`. The runtime `FunctionInvoker` dispatches
 * directly to the static `execute()` method, passing already-evaluated AST
 * argument values.
 *
 * The `execute()` signature is intentionally not declared in the interface so
 * each function can express its exact argument shape (arity, types, defaults,
 * variadic). The {@see \FQL\Functions\FunctionRegistry} tracks implementations
 * purely by the upper-cased `name()` key.
 *
 * Example:
 * ```php
 * final class Lower implements ScalarFunction
 * {
 *     public static function name(): string { return 'LOWER'; }
 *     public static function execute(mixed $value): string {
 *         return strtolower((string) $value);
 *     }
 * }
 * ```
 */
interface ScalarFunction
{
    /**
     * Upper-cased FQL identifier under which this function is invoked
     * (`SELECT lower(name)` → `'LOWER'`). Must be unique across the registry.
     */
    public static function name(): string;

    // execute(...$args) — signature per implementation; FunctionInvoker calls directly.
}
