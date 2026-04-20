<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\Node\LimitClauseNode;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

/**
 * Parses LIMIT and OFFSET clauses. Supports both `LIMIT n OFFSET m` and the shorthand
 * `LIMIT n, m` (MySQL style) as well as a standalone `OFFSET m` which merges into an
 * existing LimitClauseNode.
 */
final class LimitOffsetParser
{
    /**
     * Parses a LIMIT clause given that the LIMIT keyword has been consumed.
     *
     * @throws ParseException
     */
    public function parseLimit(TokenStream $stream, Position $limitPosition): LimitClauseNode
    {
        $limit = (int) $stream->expect(TokenType::NUMBER_LITERAL)->value;
        $offset = null;

        // LIMIT n, m  -- MySQL-style offset shorthand
        if ($stream->consumeIf(TokenType::COMMA) !== null) {
            $offset = (int) $stream->expect(TokenType::NUMBER_LITERAL)->value;
        } elseif ($stream->peekType() === TokenType::NUMBER_LITERAL) {
            // Legacy compatibility: LIMIT n m  -- whitespace-separated offset.
            $offset = (int) $stream->consume()->value;
        }

        return new LimitClauseNode($limit, $offset, $limitPosition);
    }

    /**
     * Parses a standalone OFFSET clause. Returns the integer offset; the caller merges it
     * onto any previously collected LIMIT clause.
     *
     * @throws ParseException
     */
    public function parseOffset(TokenStream $stream): int
    {
        return (int) $stream->expect(TokenType::NUMBER_LITERAL)->value;
    }
}
