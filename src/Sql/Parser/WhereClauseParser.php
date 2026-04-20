<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\Node\WhereClauseNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

final class WhereClauseParser
{
    public function __construct(private readonly ConditionGroupParser $conditionGroupParser)
    {
    }

    /**
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $whereKeyword): WhereClauseNode
    {
        $group = $this->conditionGroupParser->parseGroup(
            $stream,
            ClauseBoundary::isControlKeyword(...)
        );
        return new WhereClauseNode($group, $whereKeyword->position);
    }
}
