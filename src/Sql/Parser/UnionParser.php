<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\Node\UnionClauseNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

final class UnionParser
{
    private StatementParser $statementParser;

    public function setStatementParser(StatementParser $parser): void
    {
        $this->statementParser = $parser;
    }

    /**
     * Parses `UNION [ALL] <select-statement>` given that the UNION keyword has been
     * consumed.
     *
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $unionKeyword): UnionClauseNode
    {
        $all = $stream->consumeIf(TokenType::KEYWORD_ALL) !== null;
        $rightHand = $this->statementParser->parse($stream);
        return new UnionClauseNode($rightHand, $all, $unionKeyword->position);
    }
}
