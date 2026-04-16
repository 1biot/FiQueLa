<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Enum\Type;
use FQL\Sql\Token\Position;

final readonly class LiteralNode implements ExpressionNode
{
    public function __construct(
        public mixed $value,
        public Type $type,
        public string $raw,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
