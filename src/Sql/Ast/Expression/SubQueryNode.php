<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class SubQueryNode implements ExpressionNode, HasSpan
{
    public function __construct(
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
