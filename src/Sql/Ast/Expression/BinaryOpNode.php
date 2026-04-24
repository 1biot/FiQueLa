<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Token\Position;

/**
 * Binary arithmetic expression: `<left> <operator> <right>`.
 *
 * Supported operators (see {@see BinaryOperator}):
 *   `+` (add), `-` (subtract), `*` (multiply), `/` (divide), `%` (modulo).
 *
 * The builder maps each operator to the corresponding fluent Query method
 * (ADD/SUB/MULTIPLY/DIVIDE/MOD) so runtime evaluation reuses the existing
 * function infrastructure.
 */
final readonly class BinaryOpNode implements ExpressionNode
{
    public function __construct(
        public ExpressionNode $left,
        public BinaryOperator $operator,
        public ExpressionNode $right,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
