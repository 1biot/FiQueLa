<?php

namespace FQL\Sql\Parser;

use FQL\Enum\Sort;
use FQL\Sql\Ast\Node\OrderByClauseNode;
use FQL\Sql\Ast\Node\OrderByItemNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

final class OrderByClauseParser
{
    public function __construct(private readonly ExpressionParser $expressionParser)
    {
    }

    /**
     * Parses `ORDER BY expr [ASC|DESC] [, expr [ASC|DESC]]...`. The ORDER keyword must
     * already be consumed; this method expects the `BY` token next.
     *
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $orderKeyword): OrderByClauseNode
    {
        $stream->expect(TokenType::KEYWORD_BY);

        $items = [];
        $items[] = $this->parseItem($stream);
        while ($stream->consumeIf(TokenType::COMMA) !== null) {
            $items[] = $this->parseItem($stream);
        }

        if (!ClauseBoundary::isControlKeyword($stream->peek())) {
            throw ParseException::context(
                $stream->peek(),
                'ORDER BY clause (expected comma or end of clause)'
            );
        }

        return new OrderByClauseNode($items, $orderKeyword->position);
    }

    /**
     * @throws ParseException
     */
    private function parseItem(TokenStream $stream): OrderByItemNode
    {
        $startPosition = $stream->peek()->position;
        $expression = $this->expressionParser->parseExpression($stream);
        $direction = Sort::ASC;
        if ($stream->consumeIf(TokenType::KEYWORD_ASC) !== null) {
            $direction = Sort::ASC;
        } elseif ($stream->consumeIf(TokenType::KEYWORD_DESC) !== null) {
            $direction = Sort::DESC;
        }
        return new OrderByItemNode($expression, $direction, $startPosition);
    }
}
