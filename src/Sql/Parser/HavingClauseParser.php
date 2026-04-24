<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\Node\HavingClauseNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;

final class HavingClauseParser
{
    public function __construct(private readonly ConditionGroupParser $conditionGroupParser)
    {
    }

    /**
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $havingKeyword): HavingClauseNode
    {
        $group = $this->conditionGroupParser->parseGroup(
            $stream,
            ClauseBoundary::isControlKeyword(...)
        );
        return new HavingClauseNode($group, $havingKeyword->position);
    }
}
