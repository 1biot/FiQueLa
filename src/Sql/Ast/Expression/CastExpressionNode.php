<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Enum\Type;
use FQL\Sql\Token\Position;

final readonly class CastExpressionNode implements ExpressionNode
{
    public function __construct(
        public ExpressionNode $value,
        public Type $targetType,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
