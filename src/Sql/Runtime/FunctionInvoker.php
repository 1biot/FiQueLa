<?php

namespace FQL\Sql\Runtime;

use FQL\Exception;
use FQL\Functions\FunctionRegistry;

/**
 * Thin dispatcher that resolves an FQL function name against
 * {@see FunctionRegistry} and calls its static `execute()` with already-
 * evaluated argument values.
 *
 * Aggregate names are not dispatched here — aggregates are handled by the
 * grouping phase in {@see \FQL\Results\Stream} via the
 * {@see \FQL\Functions\Core\AggregateFunction} contract (initial /
 * accumulate / finalize). Row-by-row evaluation of an aggregate throws.
 *
 * Unknown function names raise {@see Exception\UnknownFunctionException} at
 * runtime — the parser happily produces a `FunctionCallNode` for any
 * identifier followed by `(`, so resolution is intentionally deferred.
 */
final class FunctionInvoker
{
    /**
     * @param mixed[] $args already-evaluated argument values
     * @throws Exception\UnexpectedValueException when the function is an aggregate
     * @throws Exception\UnknownFunctionException when the name is not registered
     */
    public function invoke(string $name, array $args): mixed
    {
        if (FunctionRegistry::isAggregate($name)) {
            throw new Exception\UnexpectedValueException(
                sprintf(
                    'Aggregate function %s cannot be invoked row-by-row; '
                    . 'use the grouping phase (AggregateFunction).',
                    $name
                )
            );
        }

        $class = FunctionRegistry::getScalar($name);
        if ($class === null) {
            $snapshot = FunctionRegistry::all();
            throw Exception\UnknownFunctionException::create(
                $name,
                array_merge(array_keys($snapshot['scalar']), array_keys($snapshot['aggregate']))
            );
        }

        /** @var callable $callable */
        $callable = [$class, 'execute'];
        return $callable(...$args);
    }

    public function has(string $name): bool
    {
        return FunctionRegistry::has($name);
    }

    public function isAggregate(string $name): bool
    {
        return FunctionRegistry::isAggregate($name);
    }
}
