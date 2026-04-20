<?php

namespace FQL\Sql;

/**
 * Public entry point for the FQL Sql pipeline.
 *
 * Use `compile($sql)` to obtain a `Compiler` that exposes the full pipeline (tokens,
 * AST, Query). The typical consumer is `Query\Provider::fql()`, which simply delegates
 * here.
 */
final class Provider
{
    public static function compile(string $sql, ?string $basePath = null): Compiler
    {
        return new Compiler(trim($sql), $basePath);
    }
}
