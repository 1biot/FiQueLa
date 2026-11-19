<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Enum\Operator;
use FQL\Enum\Type;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

/**
 * Single condition: <left> <operator> <right>
 *
 * `right` may be:
 *  - an ExpressionNode (e.g. literal, column reference, function call) for `=`, `<`, `LIKE`, ...
 *  - an array of ExpressionNode for `IN` / `NOT IN`
 *  - a 2-element array for `BETWEEN` / `NOT BETWEEN`
 *  - an Enum\Type value for `IS` / `IS NOT` (NULL/BOOLEAN/NUMBER/...)
 */
final readonly class ConditionExpressionNode implements ExpressionNode, HasSpan
{
    /**
     * @param ExpressionNode|Type|ExpressionNode[] $right
     */
    public function __construct(
        public ExpressionNode $left,
        public Operator $operator,
        public ExpressionNode|Type|array $right,
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
     * Copy with another `left` (and no span: it no longer matches the source).
     */
    public function withLeft(ExpressionNode $left): self
    {
        return new self(
            $left,
            $this->operator,
            $this->right,
            $this->position
        );
    }

    /**
     * Copy with another `operator` (and no span: it no longer matches the source).
     */
    public function withOperator(Operator $operator): self
    {
        return new self(
            $this->left,
            $operator,
            $this->right,
            $this->position
        );
    }

    /**
     * Copy with another `right` (and no span: it no longer matches the source).
     *
     * @param ExpressionNode|Type|ExpressionNode[] $right
     */
    public function withRight(ExpressionNode|Type|array $right): self
    {
        return new self(
            $this->left,
            $this->operator,
            $right,
            $this->position
        );
    }
}
