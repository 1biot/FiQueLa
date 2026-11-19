<?php

namespace FQL\Sql\Parser;

use FQL\Exception;
use FQL\Sql\Token\Span;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenType;

/**
 * Thrown by the parser/token stream when the input does not match the expected grammar.
 *
 * Carries the offending token and the list of types that were expected, allowing tooling
 * (REPL, IDE, error reporters) to render rich diagnostics with line/column information.
 *
 * As a {@see Exception\SourceAwareException}: rule `syntax-error`, the span of the
 * offending token (bytes of the parsed string), the reason without the
 * ` at line X, column Y` suffix, and params:
 *
 *  - `kind`: `unexpected-token`, `unexpected-character`, `unexpected-operator`,
 *    `unterminated-string`, `unterminated-identifier` or `unterminated-comment`;
 *  - `token` / `tokenType`: the offending text and its token type (`EOF` at the end
 *    of the input and for errors the tokenizer raises);
 *  - `expected`: token types that would have been accepted (may be empty);
 *  - `context`: what was being parsed, for errors raised with {@see context()}.
 */
class ParseException extends Exception\UnexpectedValueException
{
    /**
     * @param TokenType[] $expected
     * @param array<string, mixed> $params added to / overriding the default params
     */
    public function __construct(
        public readonly Token $token,
        public readonly array $expected,
        string $message,
        ?string $reason = null,
        array $params = []
    ) {
        parent::__construct($message);
        $this->describeSource(
            'syntax-error',
            $params + [
                'kind' => 'unexpected-token',
                'token' => $token->raw,
                'tokenType' => $token->type->value,
                'expected' => array_values(array_map(static fn (TokenType $t): string => $t->value, $expected)),
            ],
            $reason ?? (string) preg_replace('/ at line \d+, column \d+$/', '', $message)
        );
    }

    public static function unexpected(Token $actual, TokenType ...$expected): self
    {
        $expectedList = implode('|', array_map(static fn (TokenType $t): string => $t->value, $expected));
        $reason = sprintf('Expected %s, got %s ("%s")', $expectedList, $actual->type->value, $actual->raw);

        return new self($actual, $expected, $reason . ' at ' . $actual->position, $reason);
    }

    public static function context(Token $actual, string $context): self
    {
        $reason = sprintf('Unexpected %s ("%s") in %s', $actual->type->value, $actual->raw, $context);

        return new self($actual, [], $reason . ' at ' . $actual->position, $reason, ['context' => $context]);
    }

    public function getRule(): ?string
    {
        return 'syntax-error';
    }

    /**
     * A span attached with {@see attachSpan()}, otherwise the span of the offending
     * token; null for a token with a synthetic position.
     */
    public function getSpan(): ?Span
    {
        $attached = parent::getSpan();
        $position = $this->token->position;
        if ($attached !== null || $position->line < 1 || $position->offset < 0) {
            return $attached;
        }
        return new Span($position->offset, $position->offset + max(0, $this->token->length));
    }
}
