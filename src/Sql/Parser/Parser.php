<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Token\TokenStream;

/**
 * Top-level FQL parser facade. Wires all clause / expression parsers together and
 * exposes a single `parse()` entry point that converts a TokenStream into an AST
 * (SelectStatementNode).
 *
 * Construct via `Parser::create()` for the default configuration; custom assembly is
 * possible via the constructor for testing or extension.
 */
final class Parser
{
    public function __construct(private readonly StatementParser $statementParser)
    {
    }

    /**
     * Builds the default parser with every clause/expression sub-parser wired up.
     */
    public static function create(): self
    {
        $expressionParser = new ExpressionParser();
        $conditionParser = new ConditionParser($expressionParser);
        $conditionGroupParser = new ConditionGroupParser($conditionParser);
        // ExpressionParser needs the group parser for CASE WHEN branches.
        $expressionParser->setConditionGroupParser($conditionGroupParser);

        $selectParser = new SelectClauseParser($expressionParser);
        $fromParser = new FromClauseParser();
        $joinParser = new JoinClauseParser($fromParser, $conditionParser);
        $whereParser = new WhereClauseParser($conditionGroupParser);
        $havingParser = new HavingClauseParser($conditionGroupParser);
        $groupByParser = new GroupByClauseParser($expressionParser);
        $orderByParser = new OrderByClauseParser($expressionParser);
        $limitParser = new LimitOffsetParser();
        $unionParser = new UnionParser();
        $intoParser = new IntoParser();

        $statementParser = new StatementParser(
            $selectParser,
            $fromParser,
            $joinParser,
            $whereParser,
            $havingParser,
            $groupByParser,
            $orderByParser,
            $limitParser,
            $unionParser,
            $intoParser
        );

        // FromClauseParser and UnionParser need the StatementParser for nested statements.
        $fromParser->setStatementParser($statementParser);
        $unionParser->setStatementParser($statementParser);

        return new self($statementParser);
    }

    /**
     * @throws ParseException
     */
    public function parse(TokenStream $stream): SelectStatementNode
    {
        return $this->statementParser->parse($stream);
    }
}
