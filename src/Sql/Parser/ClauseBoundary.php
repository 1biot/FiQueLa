<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenType;

/**
 * Helper predicates used by clause parsers to decide where their clause ends.
 *
 * These are plain static callables rather than callback closures to keep
 * clause parsers readable and avoid per-invocation allocations.
 */
final class ClauseBoundary
{
    /**
     * True when the token terminates the current clause: either a top-level
     * clause-starting keyword (WHERE ends at GROUP/HAVING/ORDER/LIMIT/etc.) or
     * a structural boundary — `PAREN_CLOSE` closes a subquery / CTE body and
     * implicitly ends the last clause inside it, EOF ends the top-level
     * statement.
     */
    public static function isControlKeyword(Token $token): bool
    {
        return match ($token->type) {
            TokenType::KEYWORD_FROM,
            TokenType::KEYWORD_WHERE,
            TokenType::KEYWORD_GROUP,
            TokenType::KEYWORD_HAVING,
            TokenType::KEYWORD_ORDER,
            TokenType::KEYWORD_LIMIT,
            TokenType::KEYWORD_OFFSET,
            TokenType::KEYWORD_UNION,
            TokenType::KEYWORD_INTO,
            TokenType::KEYWORD_INNER,
            TokenType::KEYWORD_LEFT,
            TokenType::KEYWORD_RIGHT,
            TokenType::KEYWORD_FULL,
            TokenType::KEYWORD_JOIN,
            TokenType::PAREN_CLOSE,
            TokenType::EOF => true,
            default => false,
        };
    }
}
