<?php

namespace FQL\Sql\Builder;

use FQL\Enum;
use FQL\Exception;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\MatchAgainstNode;
use FQL\Sql\Ast\Expression\StarNode;

/**
 * Converts AST ExpressionNode / ConditionGroupNode trees back into FQL string form.
 *
 * Used by the query builder for:
 *  - `whenCase($conditionString, $thenStatement)` — the existing `Interface\Query` API
 *    accepts pre-rendered strings, so CASE branches must be serialised back to SQL.
 *  - `SimpleCondition::value` storage — scalar extraction from an ExpressionNode, so
 *    condition evaluation at runtime has the same shape as the legacy parser produced.
 */
final class ExpressionCompiler
{
    /**
     * Serialises an expression into FQL-string form (the form accepted by the legacy
     * `whenCase`/`if`/`case` APIs that take strings).
     */
    public function renderExpression(ExpressionNode $node): string
    {
        if ($node instanceof LiteralNode) {
            return $this->renderLiteral($node);
        }
        if ($node instanceof ColumnReferenceNode) {
            return $node->name;
        }
        if ($node instanceof StarNode) {
            return '*';
        }
        if ($node instanceof FunctionCallNode) {
            $prefix = $node->distinct ? 'DISTINCT ' : '';
            $args = array_map(fn (ExpressionNode $a): string => $this->renderExpression($a), $node->arguments);
            return $node->name . '(' . $prefix . implode(', ', $args) . ')';
        }
        if ($node instanceof CastExpressionNode) {
            return 'CAST(' . $this->renderExpression($node->value) . ' AS ' . strtoupper($node->targetType->value) . ')';
        }
        if ($node instanceof MatchAgainstNode) {
            $fields = implode(', ', array_map(static fn (ColumnReferenceNode $f): string => $f->name, $node->fields));
            return sprintf(
                'MATCH(%s) AGAINST("%s IN %s MODE")',
                $fields,
                $node->searchQuery,
                $node->mode->value
            );
        }
        throw new Exception\QueryLogicException(
            sprintf('Cannot render expression of type %s', get_class($node))
        );
    }

    /**
     * Serialises a LiteralNode back to its FQL textual form (numbers unchanged, strings
     * wrapped in double quotes, booleans/null keyword-cased).
     */
    public function renderLiteral(LiteralNode $node): string
    {
        if ($node->value === null) {
            return 'NULL';
        }
        if (is_bool($node->value)) {
            return $node->value ? 'TRUE' : 'FALSE';
        }
        if (is_string($node->value)) {
            return '"' . $node->value . '"';
        }
        return (string) $node->value;
    }

    /**
     * Renders a ConditionGroupNode into the FQL WHERE-style form (e.g. `a = 1 AND b > 2`)
     * used by whenCase() and similar legacy APIs.
     */
    public function renderConditionGroup(ConditionGroupNode $group): string
    {
        $out = '';
        foreach ($group->entries as $index => $entry) {
            if ($index > 0) {
                $out .= $entry['logical']->render(true);
            }
            $condition = $entry['condition'];
            if ($condition instanceof ConditionGroupNode) {
                $out .= '(' . $this->renderConditionGroup($condition) . ')';
            } else {
                $out .= $this->renderCondition($condition);
            }
        }
        return $out;
    }

    public function renderCondition(ConditionExpressionNode $condition): string
    {
        $field = $this->renderExpression($condition->left);
        $right = $this->renderRightForOperator($condition->right, $condition->operator);
        return $condition->operator->render($field, $right);
    }

    /**
     * @param ExpressionNode|Enum\Type|ExpressionNode[] $right
     * @return array<int, mixed>|float|int|string|Enum\Type
     */
    public function scalarRightValue(
        ExpressionNode|Enum\Type|array $right,
        Enum\Operator $operator
    ): array|float|int|string|Enum\Type {
        if ($right instanceof Enum\Type) {
            return $right;
        }
        if (is_array($right)) {
            // IN / BETWEEN — scalar list
            return array_map(fn (ExpressionNode $n): float|int|string => $this->scalarOf($n), $right);
        }
        return $this->scalarOf($right);
    }

    /**
     * @return int|float|string
     */
    private function scalarOf(ExpressionNode $node): int|float|string
    {
        if ($node instanceof LiteralNode) {
            $value = $node->value;
            if (is_int($value) || is_float($value) || is_string($value)) {
                return $value;
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            return '';
        }
        if ($node instanceof ColumnReferenceNode) {
            return $node->name;
        }
        if ($node instanceof StarNode) {
            return '*';
        }
        return $this->renderExpression($node);
    }

    /**
     * Prepares the right-hand side value in the shape expected by `Operator::render()`.
     *
     * @param ExpressionNode|Enum\Type|ExpressionNode[] $right
     * @return array<int, string|int|float>|float|int|string|Enum\Type
     */
    private function renderRightForOperator(
        ExpressionNode|Enum\Type|array $right,
        Enum\Operator $operator
    ): array|float|int|string|Enum\Type {
        if ($right instanceof Enum\Type) {
            return $right;
        }
        if (is_array($right)) {
            return array_map(fn (ExpressionNode $n): int|float|string => $this->scalarOf($n), $right);
        }
        return $this->scalarOf($right);
    }
}
