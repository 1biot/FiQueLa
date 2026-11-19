<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

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
final readonly class BinaryOpNode implements ExpressionNode, HasSpan
{
    public function __construct(
        public ExpressionNode $left,
        public BinaryOperator $operator,
        public ExpressionNode $right,
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
