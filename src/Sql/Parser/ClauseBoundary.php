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
     * True when the token is one of the top-level clause-starting keywords that
     * terminate the current clause (WHERE ends at GROUP/HAVING/ORDER/LIMIT/etc.).
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
            TokenType::EOF => true,
            default => false,
        };
    }
}
