<?php

namespace FQL\Conditions;

use FQL\Enum;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Builder\ExpressionCompiler;
use FQL\Sql\Runtime\ExpressionEvaluator;

/**
 * Condition whose operands are full `ExpressionNode` trees rather than field-name
 * strings — the runtime counterpart to `SimpleCondition` used whenever the parser
 * builds a WHERE/HAVING clause from an SQL string.
 *
 * Unlike `SimpleCondition`, which relies on a dual-mode field lookup via
 * `$nestingValues`, this class delegates all value resolution to the injected
 * `ExpressionEvaluator`. The `$nestingValues` flag is accepted for interface
 * compatibility but ignored — the evaluator handles nested access, literal
 * fallbacks and function evaluation uniformly.
 *
 * Right-hand side is polymorphic to mirror the AST:
 *   - `ExpressionNode` — single value comparison (`=`, `<`, `LIKE`, ...)
 *   - `Enum\Type`      — type introspection (`IS NULL`, `IS NUMBER`, ...)
 *   - `ExpressionNode[]` — value list for `IN` / `NOT IN` and range for `BETWEEN`
 */
final class ExpressionCondition extends Condition
{
    /**
     * @param ExpressionNode|Enum\Type|ExpressionNode[] $right
     */
    public function __construct(
        Enum\LogicalOperator $logicalOperator,
        public readonly ExpressionNode $left,
        public readonly Enum\Operator $operator,
        public readonly ExpressionNode|Enum\Type|array $right,
        private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator()
    ) {
        parent::__construct($logicalOperator);
    }

    /**
     * @inheritDoc
     */
    public function evaluate(array $item, bool $nestingValues): bool
    {
        unset($nestingValues); // evaluator handles nested lookups uniformly
        $leftValue = $this->evaluator->evaluate($this->left, $item);
        $rightValue = $this->resolveRightValue($item);
        return $this->operator->evaluate($leftValue, $rightValue);
    }

    /**
     * Human-readable SQL-like form, useful for `__toString` on condition groups and
     * for debug output. Reuses the AST-aware `ExpressionCompiler`.
     */
    public function render(): string
    {
        $compiler = new ExpressionCompiler();
        $fieldString = $compiler->renderExpression($this->left);

        if ($this->right instanceof Enum\Type) {
            $rightRendered = $this->right;
        } elseif (is_array($this->right)) {
            $rightRendered = array_map(
                fn (ExpressionNode $n): string => $compiler->renderExpression($n),
                $this->right
            );
        } else {
            $rightRendered = $compiler->renderExpression($this->right);
        }

        return $this->operator->render($fieldString, $rightRendered);
    }

    /**
     * @param array<int|string, mixed> $item
     * @return mixed|mixed[]|Enum\Type
     */
    private function resolveRightValue(array $item): mixed
    {
        if ($this->right instanceof Enum\Type) {
            return $this->right;
        }
        if (is_array($this->right)) {
            return array_map(
                fn (ExpressionNode $n): mixed => $this->evaluator->evaluate($n, $item),
                $this->right
            );
        }
        return $this->evaluator->evaluate($this->right, $item);
    }
}
