<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\Node\CommonTableExpressionNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

/**
 * Parses a `WITH name AS (SELECT ...) [, name2 AS (SELECT ...) ...]` clause.
 *
 * Only one WITH keyword may appear per statement, but it may declare an unlimited
 * number of comma-separated CTEs. Later CTEs may reference earlier ones (forward-
 * only chaining); recursive CTEs (`WITH RECURSIVE ...`) are explicitly rejected.
 *
 * The parser cooperates with {@see FromClauseParser}: as each CTE body is parsed,
 * the previously declared CTE names are registered so that nested `FROM cte_a`
 * references resolve. After the WITH clause finishes, the caller is expected to
 * extend the registered names to include every CTE in the clause so that the
 * main SELECT body can reference them too.
 *
 * Wired with a circular dependency on {@see StatementParser} via {@see setStatementParser()},
 * mirroring the pattern used by {@see FromClauseParser} and {@see UnionParser}.
 */
final class WithClauseParser
{
    private StatementParser $statementParser;

    public function __construct(private readonly FromClauseParser $fromParser)
    {
    }

    public function setStatementParser(StatementParser $parser): void
    {
        $this->statementParser = $parser;
    }

    /**
     * Parses the body of the WITH clause given that the WITH keyword has been consumed.
     *
     * @return CommonTableExpressionNode[]
     *
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $withKeyword): array
    {
        if ($stream->consumeIf(TokenType::KEYWORD_RECURSIVE) !== null) {
            throw ParseException::context($withKeyword, 'WITH (RECURSIVE CTEs are not supported)');
        }

        $outerKnown = $this->fromParser->getKnownCteNames();

        /** @var CommonTableExpressionNode[] $ctes */
        $ctes = [];
        $seen = [];

        try {
            do {
                $nameToken = $stream->expect(TokenType::IDENTIFIER, TokenType::IDENTIFIER_QUOTED);
                $name = IdentifierHelper::stripOuterBackticks($nameToken->value);

                if (isset($seen[$name])) {
                    throw ParseException::context(
                        $nameToken,
                        sprintf('WITH (duplicate CTE name "%s")', $name)
                    );
                }
                $seen[$name] = true;

                $stream->expect(TokenType::KEYWORD_AS);
                $stream->expect(TokenType::PAREN_OPEN);
                // Forward-chaining: previously declared CTEs in this WITH are visible
                // inside the body of the next CTE.
                $this->fromParser->setKnownCteNames(array_merge($outerKnown, array_keys($seen)));
                $select = $this->statementParser->parse($stream);
                $stream->expect(TokenType::PAREN_CLOSE);

                $ctes[] = new CommonTableExpressionNode($name, $select, $nameToken->position);
            } while ($stream->consumeIf(TokenType::COMMA) !== null);
        } finally {
            // Restore to outer scope; the caller is responsible for re-registering the
            // full CTE list before parsing the main SELECT body.
            $this->fromParser->setKnownCteNames($outerKnown);
        }

        return $ctes;
    }
}
