<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\Node\GroupByClauseNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

final class GroupByClauseParser
{
    public function __construct(private readonly ExpressionParser $expressionParser)
    {
    }

    /**
     * Parses `GROUP BY a, b, c`. The GROUP keyword must already be consumed; this method
     * expects the `BY` token next.
     *
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $groupKeyword): GroupByClauseNode
    {
        $stream->expect(TokenType::KEYWORD_BY);
        $fields = [$this->expressionParser->parsePrimary($stream)];
        while ($stream->consumeIf(TokenType::COMMA) !== null) {
            $fields[] = $this->expressionParser->parsePrimary($stream);
        }

        if (!ClauseBoundary::isControlKeyword($stream->peek())) {
            throw ParseException::context(
                $stream->peek(),
                'GROUP BY clause (Expected comma between GROUP BY fields)'
            );
        }

        return new GroupByClauseNode($fields, $groupKeyword->position);
    }
}
