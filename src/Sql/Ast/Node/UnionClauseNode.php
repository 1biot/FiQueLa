<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class UnionClauseNode implements AstNode, HasSpan
{
    public function __construct(
        public SelectStatementNode $query,
        public bool $all,
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
