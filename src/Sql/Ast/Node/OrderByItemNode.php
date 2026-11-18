<?php

namespace FQL\Sql\Ast\Node;

use FQL\Enum\Sort;
use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class OrderByItemNode implements AstNode, HasSpan
{
    public function __construct(
        public ExpressionNode $expression,
        public Sort $direction,
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

    /**
     * Copy with another `expression` (and no span: it no longer matches the source).
     */
    public function withExpression(ExpressionNode $expression): self
    {
        return new self(
            $expression,
            $this->direction,
            $this->position
        );
    }

    /**
     * Copy with another `direction` (and no span: it no longer matches the source).
     */
    public function withDirection(Sort $direction): self
    {
        return new self(
            $this->expression,
            $direction,
            $this->position
        );
    }
}
