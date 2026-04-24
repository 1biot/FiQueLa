<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Enum\Operator;
use FQL\Enum\Type;
use FQL\Sql\Token\Position;

/**
 * Single condition: <left> <operator> <right>
 *
 * `right` may be:
 *  - an ExpressionNode (e.g. literal, column reference, function call) for `=`, `<`, `LIKE`, ...
 *  - an array of ExpressionNode for `IN` / `NOT IN`
 *  - a 2-element array for `BETWEEN` / `NOT BETWEEN`
 *  - an Enum\Type value for `IS` / `IS NOT` (NULL/BOOLEAN/NUMBER/...)
 */
final readonly class ConditionExpressionNode implements ExpressionNode
{
    /**
     * @param ExpressionNode|Type|ExpressionNode[] $right
     */
    public function __construct(
        public ExpressionNode $left,
        public Operator $operator,
        public ExpressionNode|Type|array $right,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
