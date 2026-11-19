<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class CaseExpressionNode implements ExpressionNode, HasSpan
{
    /**
     * @param WhenBranchNode[] $branches
     */
    public function __construct(
        public array $branches,
        public ?ExpressionNode $else,
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
