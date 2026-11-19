<?php

namespace FQL\Exception;

/**
 * Thrown when the runtime evaluator tries to invoke a function name that is
 * not present in {@see \FQL\Functions\FunctionRegistry}.
 *
 * The name is raised at runtime (during row evaluation) rather than at parse
 * or build time — the parser happily produces a `FunctionCallNode` for any
 * identifier followed by `(`, and deferred resolution allows users to register
 * functions after the query is built. Because it is raised during execution it
 * carries no span; lint (`unknown-function`) reports the same name with a span
 * before the query runs.
 *
 * Rule `unknown-function`, params `['function' => …]`; the registered names are in
 * {@see getRegisteredFunctions()}, not in the params or the reason.
 */
class UnknownFunctionException extends UnexpectedValueException
{
    /** @var list<string> */
    private array $registeredFunctions = [];

    /**
     * @param string[] $registered upper-cased names currently in the registry
     */
    public static function create(string $name, array $registered): self
    {
        sort($registered);
        $list = $registered === [] ? '(none)' : implode(', ', $registered);
        $exception = new self(sprintf(
            'Unknown FQL function "%s". Registered functions: %s',
            $name,
            $list
        ));
        $exception->registeredFunctions = array_values($registered);
        $exception->describeSource(
            'unknown-function',
            ['function' => $name],
            sprintf('Unknown FQL function "%s"', $name)
        );
        return $exception;
    }

    public function getRule(): ?string
    {
        return 'unknown-function';
    }

    /**
     * Function names registered when the exception was raised, sorted.
     *
     * @return list<string>
     */
    public function getRegisteredFunctions(): array
    {
        return $this->registeredFunctions;
    }
}
