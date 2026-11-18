<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Enum\Type;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class CastExpressionNode implements ExpressionNode, HasSpan
{
    public function __construct(
        public ExpressionNode $value,
        public Type $targetType,
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
