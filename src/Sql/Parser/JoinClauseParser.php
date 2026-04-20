<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\JoinType;
use FQL\Sql\Ast\Node\JoinClauseNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

final class JoinClauseParser
{
    public function __construct(
        private readonly FromClauseParser $fromClauseParser,
        private readonly ConditionParser $conditionParser
    ) {
    }

    /**
     * Parses a full JOIN clause given the opening token (INNER/LEFT/RIGHT/FULL/JOIN)
     * that has already been consumed.
     *
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $leadToken): JoinClauseNode
    {
        $joinType = match ($leadToken->type) {
            TokenType::KEYWORD_INNER => JoinType::INNER,
            TokenType::KEYWORD_LEFT => JoinType::LEFT,
            TokenType::KEYWORD_RIGHT => JoinType::RIGHT,
            TokenType::KEYWORD_FULL => JoinType::FULL,
            TokenType::KEYWORD_JOIN => JoinType::INNER,
            default => throw ParseException::context($leadToken, 'JOIN clause'),
        };

        // INNER/LEFT/RIGHT/FULL may be followed by optional OUTER, then JOIN.
        if ($leadToken->type !== TokenType::KEYWORD_JOIN) {
            $stream->consumeIf(TokenType::KEYWORD_OUTER);
            $stream->expect(TokenType::KEYWORD_JOIN);
        }

        $source = $this->fromClauseParser->parseSource($stream);
        $stream->expect(TokenType::KEYWORD_AS);
        $aliasToken = $stream->expect(TokenType::IDENTIFIER, TokenType::IDENTIFIER_QUOTED);

        $stream->expect(TokenType::KEYWORD_ON);
        $condition = $this->conditionParser->parse($stream);

        return new JoinClauseNode($joinType, $source, $aliasToken->value, $condition, $leadToken->position);
    }
}
