<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Token\Position;

final readonly class ColumnReferenceNode implements ExpressionNode
{
    public function __construct(
        public string $name,
        public Position $position,
        public bool $quoted = false
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
