<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\ExplainMode;
use FQL\Sql\Token\Position;

/**
 * Root node for any FQL statement: SELECT, DESCRIBE, or EXPLAIN [ANALYZE] SELECT.
 *
 * `from` is required for SELECT/DESCRIBE; it carries the source reference.
 * `fields` is empty for DESCRIBE (the schema introspection statement).
 */
final readonly class SelectStatementNode implements AstNode
{
    /**
     * @param SelectFieldNode[] $fields
     * @param JoinClauseNode[]  $joins
     * @param UnionClauseNode[] $unions
     */
    public function __construct(
        public FromClauseNode $from,
        public array $fields,
        public bool $distinct,
        public array $joins,
        public ?WhereClauseNode $where,
        public ?GroupByClauseNode $groupBy,
        public ?HavingClauseNode $having,
        public ?OrderByClauseNode $orderBy,
        public ?LimitClauseNode $limit,
        public array $unions,
        public ?IntoClauseNode $into,
        public bool $describe,
        public ExplainMode $explain,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
