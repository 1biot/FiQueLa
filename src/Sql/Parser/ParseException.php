<?php

namespace FQL\Sql\Parser;

use FQL\Exception;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenType;

/**
 * Thrown by the parser/token stream when the input does not match the expected grammar.
 *
 * Carries the offending token and the list of types that were expected, allowing tooling
 * (REPL, IDE, error reporters) to render rich diagnostics with line/column information.
 */
class ParseException extends Exception\UnexpectedValueException
{
    /**
     * @param TokenType[] $expected
     */
    public function __construct(
        public readonly Token $token,
        public readonly array $expected,
        string $message
    ) {
        parent::__construct($message);
    }

    public static function unexpected(Token $actual, TokenType ...$expected): self
    {
        $expectedList = implode('|', array_map(static fn (TokenType $t): string => $t->value, $expected));
        $message = sprintf(
            'Expected %s, got %s ("%s") at %s',
            $expectedList,
            $actual->type->value,
            $actual->raw,
            (string) $actual->position
        );

        return new self($actual, $expected, $message);
    }

    public static function context(Token $actual, string $context): self
    {
        $message = sprintf(
            'Unexpected %s ("%s") in %s at %s',
            $actual->type->value,
            $actual->raw,
            $context,
            (string) $actual->position
        );

        return new self($actual, [], $message);
    }
}
