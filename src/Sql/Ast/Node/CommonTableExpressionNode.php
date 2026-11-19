<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

/**
 * Single Common Table Expression: `name AS (SELECT ...)`.
 *
 * Multiple CTEs in one WITH clause are stored as `CommonTableExpressionNode[]`
 * on {@see SelectStatementNode::$commonTables}.
 */
final readonly class CommonTableExpressionNode implements AstNode, HasSpan
{
    public function __construct(
        public string $name,
        public SelectStatementNode $query,
        public Position $position,
        public ?Span $span = null
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function span(): ?Span
    {
        return $this->span;
    }
}
