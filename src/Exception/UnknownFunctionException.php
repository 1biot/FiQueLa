<?php

namespace FQL\Exception;

/**
 * Thrown when the runtime evaluator tries to invoke a function name that is
 * not present in {@see \FQL\Functions\FunctionRegistry}.
 *
 * The name is raised at runtime (during row evaluation) rather than at parse
 * or build time — the parser happily produces a `FunctionCallNode` for any
 * identifier followed by `(`, and deferred resolution allows users to register
 * functions after the query is built.
 */
class UnknownFunctionException extends UnexpectedValueException
{
    /**
     * @param string[] $registered upper-cased names currently in the registry
     */
    public static function create(string $name, array $registered): self
    {
        sort($registered);
        $list = $registered === [] ? '(none)' : implode(', ', $registered);
        return new self(sprintf(
            'Unknown FQL function "%s". Registered functions: %s',
            $name,
            $list
        ));
    }
}
