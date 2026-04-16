<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Token\Position;

final readonly class StarNode implements ExpressionNode
{
    public function __construct(public Position $position)
    {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
