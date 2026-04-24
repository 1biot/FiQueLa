<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\ExplainMode;
use FQL\Sql\Ast\Node\FromClauseNode;
use FQL\Sql\Ast\Node\GroupByClauseNode;
use FQL\Sql\Ast\Node\HavingClauseNode;
use FQL\Sql\Ast\Node\IntoClauseNode;
use FQL\Sql\Ast\Node\JoinClauseNode;
use FQL\Sql\Ast\Node\LimitClauseNode;
use FQL\Sql\Ast\Node\OrderByClauseNode;
use FQL\Sql\Ast\Node\SelectFieldNode;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Ast\Node\UnionClauseNode;
use FQL\Sql\Ast\Node\WhereClauseNode;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

/**
 * Top-level recursive-descent parser that assembles a SelectStatementNode from a
 * stream of tokens. Delegates each clause to a dedicated clause parser.
 *
 * The implementation reads clauses in whatever order they appear (mirroring the
 * legacy parser), which tolerates minor grammar flexibility while the clause parsers
 * themselves enforce internal structure.
 */
final class StatementParser
{
    public function __construct(
        private readonly SelectClauseParser $selectParser,
        private readonly FromClauseParser $fromParser,
        private readonly JoinClauseParser $joinParser,
        private readonly WhereClauseParser $whereParser,
        private readonly HavingClauseParser $havingParser,
        private readonly GroupByClauseParser $groupByParser,
        private readonly OrderByClauseParser $orderByParser,
        private readonly LimitOffsetParser $limitParser,
        private readonly UnionParser $unionParser,
        private readonly IntoParser $intoParser
    ) {
    }

    /**
     * Parses a single SELECT/DESCRIBE (optionally prefixed by EXPLAIN) statement.
     * Stops at `)` (subquery terminator), end of stream, or when no further clause tokens
     * are recognised.
     *
     * @throws ParseException
     */
    public function parse(TokenStream $stream): SelectStatementNode
    {
        $startPosition = $stream->peek()->position;

        $explain = ExplainMode::NONE;
        if ($stream->consumeIf(TokenType::KEYWORD_EXPLAIN) !== null) {
            $explain = $stream->consumeIf(TokenType::KEYWORD_ANALYZE) !== null
                ? ExplainMode::EXPLAIN_ANALYZE
                : ExplainMode::EXPLAIN;
        }

        // DESCRIBE statement: DESCRIBE <source> — has no field list.
        $describeToken = $stream->consumeIf(TokenType::KEYWORD_DESCRIBE);
        if ($describeToken !== null) {
            $from = $this->fromParser->parseClause($stream, $describeToken);
            return $this->buildStatement(
                position: $startPosition,
                from: $from,
                fields: [],
                distinct: false,
                describe: true,
                explain: $explain
            );
        }

        $selectKeyword = $stream->expect(TokenType::KEYWORD_SELECT);
        $selectResult = $this->selectParser->parseClause($stream, $selectKeyword);
        $fields = $selectResult['fields'];
        $distinct = $selectResult['distinct'];

        $from = null;
        /** @var JoinClauseNode[] $joins */
        $joins = [];
        $where = null;
        $groupBy = null;
        $having = null;
        $orderBy = null;
        $limit = null;
        $into = null;
        /** @var UnionClauseNode[] $unions */
        $unions = [];

        while (!$stream->isAtEnd() && $stream->peekType() !== TokenType::PAREN_CLOSE) {
            $peek = $stream->peek();
            switch ($peek->type) {
                case TokenType::KEYWORD_FROM:
                    $stream->consume();
                    $from = $this->fromParser->parseClause($stream, $peek);
                    break;

                case TokenType::KEYWORD_INNER:
                case TokenType::KEYWORD_LEFT:
                case TokenType::KEYWORD_RIGHT:
                case TokenType::KEYWORD_FULL:
                case TokenType::KEYWORD_JOIN:
                    $stream->consume();
                    $joins[] = $this->joinParser->parseClause($stream, $peek);
                    break;

                case TokenType::KEYWORD_WHERE:
                    $stream->consume();
                    $where = $this->whereParser->parseClause($stream, $peek);
                    break;

                case TokenType::KEYWORD_GROUP:
                    $stream->consume();
                    $groupBy = $this->groupByParser->parseClause($stream, $peek);
                    break;

                case TokenType::KEYWORD_HAVING:
                    $stream->consume();
                    $having = $this->havingParser->parseClause($stream, $peek);
                    break;

                case TokenType::KEYWORD_ORDER:
                    $stream->consume();
                    $orderBy = $this->orderByParser->parseClause($stream, $peek);
                    break;

                case TokenType::KEYWORD_LIMIT:
                    $stream->consume();
                    $parsed = $this->limitParser->parseLimit($stream, $peek->position);
                    // Preserve an offset that arrived via a preceding standalone
                    // OFFSET clause — `OFFSET 5 LIMIT 10` used to silently lose
                    // the offset because parseLimit returned a fresh node with
                    // offset = null that overwrote the merged one.
                    $limit = $limit !== null && $limit->offset !== null && $parsed->offset === null
                        ? new LimitClauseNode($parsed->limit, $limit->offset, $parsed->position)
                        : $parsed;
                    break;

                case TokenType::KEYWORD_OFFSET:
                    $stream->consume();
                    $offset = $this->limitParser->parseOffset($stream);
                    $limit = $this->mergeOffset($limit, $offset, $peek->position);
                    break;

                case TokenType::KEYWORD_INTO:
                    $stream->consume();
                    $into = $this->intoParser->parseClause($stream, $peek);
                    break;

                case TokenType::KEYWORD_UNION:
                    $stream->consume();
                    $unions[] = $this->unionParser->parseClause($stream, $peek);
                    // After UNION we break out of the loop — subsequent clauses (if any)
                    // belong to the right-hand side of the UNION, which is already parsed.
                    break 2;

                default:
                    throw ParseException::context($peek, 'statement (unexpected token)');
            }
        }

        // FROM is optional at parse time: a FROM-less SELECT is valid input for
        // `Compiler::applyTo($existingQuery)`, where the outer stream is already open.
        // The builder enforces the presence of FROM for full `build()` consumers.
        return $this->buildStatement(
            position: $startPosition,
            from: $from,
            fields: $fields,
            distinct: $distinct,
            joins: $joins,
            where: $where,
            groupBy: $groupBy,
            having: $having,
            orderBy: $orderBy,
            limit: $limit,
            unions: $unions,
            into: $into,
            describe: false,
            explain: $explain
        );
    }

    /**
     * @param SelectFieldNode[] $fields
     * @param JoinClauseNode[]  $joins
     * @param UnionClauseNode[] $unions
     */
    private function buildStatement(
        Position $position,
        ?FromClauseNode $from,
        array $fields,
        bool $distinct,
        array $joins = [],
        ?WhereClauseNode $where = null,
        ?GroupByClauseNode $groupBy = null,
        ?HavingClauseNode $having = null,
        ?OrderByClauseNode $orderBy = null,
        ?LimitClauseNode $limit = null,
        array $unions = [],
        ?IntoClauseNode $into = null,
        bool $describe = false,
        ExplainMode $explain = ExplainMode::NONE
    ): SelectStatementNode {
        return new SelectStatementNode(
            from: $from,
            fields: $fields,
            distinct: $distinct,
            joins: $joins,
            where: $where,
            groupBy: $groupBy,
            having: $having,
            orderBy: $orderBy,
            limit: $limit,
            unions: $unions,
            into: $into,
            describe: $describe,
            explain: $explain,
            position: $position
        );
    }

    private function mergeOffset(?LimitClauseNode $limit, int $offset, Position $position): LimitClauseNode
    {
        if ($limit === null) {
            // Bare OFFSET without LIMIT — retain zero limit; consumers treat non-zero
            // offsets as the dominant constraint.
            return new LimitClauseNode(0, $offset, $position);
        }
        return new LimitClauseNode($limit->limit, $offset, $limit->position);
    }
}
