<?php

namespace FQL\Sql\Runtime;

use FQL\Enum;
use FQL\Exception;
use FQL\Functions;
use FQL\Query\FileQuery;
use FQL\Sql\Ast\Expression\BinaryOperator;
use FQL\Sql\Ast\Expression\BinaryOpNode;
use FQL\Sql\Ast\Expression\CaseExpressionNode;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\MatchAgainstNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Traits\Helpers\EnhancedNestedArrayAccessor;
use FQL\Traits\Helpers\StringOperations;

/**
 * Evaluates an AST `ExpressionNode` tree against a concrete row (`$item`) and returns
 * the resulting value — the runtime heart of FQL 3.0.0's expression support.
 *
 * Nested function calls (`UPPER(LOWER(name))`), arithmetic (`price * 0.9`), function
 * arguments with expressions (`ROUND(5 * price, 2)`), and conditional branches in
 * WHERE/HAVING/CASE all route through here. Scalar function calls delegate to
 * {@see FunctionInvoker}; aggregates are *not* handled here — see
 * {@see ExpressionAggregate} for the grouping-phase integration.
 *
 * The evaluator is stateless between `evaluate()` calls except for the subquery
 * result cache (keyed by `spl_object_id`), which persists for the evaluator's lifetime.
 */
final class ExpressionEvaluator
{
    use EnhancedNestedArrayAccessor;
    use StringOperations;

    /** @var array<int, mixed> Memoised subquery results keyed by `spl_object_id($node)`. */
    private array $subQueryCache = [];

    public function __construct(
        private readonly FunctionInvoker $functions = new FunctionInvoker()
    ) {
    }

    /**
     * @param array<int|string, mixed> $item       source row (pre-SELECT data)
     * @param array<int|string, mixed> $resultItem accumulated SELECT result so far;
     *                                             used as fallback lookup for aliased
     *                                             fields referenced by later SELECT
     *                                             expressions or by HAVING.
     * @throws Exception\UnexpectedValueException when the AST contains an unsupported node
     */
    public function evaluate(ExpressionNode $node, array $item, array $resultItem = []): mixed
    {
        return match (true) {
            $node instanceof LiteralNode => $node->value,
            $node instanceof ColumnReferenceNode => $this->evaluateColumn($node, $item, $resultItem),
            $node instanceof StarNode => '*',
            $node instanceof FunctionCallNode => $this->evaluateFunctionCall($node, $item, $resultItem),
            $node instanceof BinaryOpNode => $this->evaluateBinaryOp($node, $item, $resultItem),
            $node instanceof CastExpressionNode => $this->evaluateCast($node, $item, $resultItem),
            $node instanceof CaseExpressionNode => $this->evaluateCase($node, $item, $resultItem),
            $node instanceof MatchAgainstNode => $this->evaluateMatchAgainst($node, $item, $resultItem),
            $node instanceof ConditionExpressionNode => $this->evaluateCondition($node, $item, $resultItem),
            $node instanceof ConditionGroupNode => $this->evaluateGroup($node, $item, $resultItem),
            $node instanceof SubQueryNode => $this->evaluateSubQuery($node),
            $node instanceof FileQueryNode => throw new Exception\QueryLogicException(
                'FileQueryNode cannot be evaluated at runtime; it is only valid in FROM/JOIN/INTO clauses.'
            ),
            default => throw new Exception\UnexpectedValueException(
                sprintf('Unsupported expression node type: %s', get_class($node))
            ),
        };
    }

    /**
     * Evaluates a single condition (`<left> <op> <right>`) and returns its boolean
     * result. Used by `CaseExpressionNode` branch evaluation and by
     * `ExpressionCondition` in the `Conditions` namespace.
     */
    /**
     * @param array<int|string, mixed> $item
     * @param array<int|string, mixed> $resultItem
     */
    public function evaluateCondition(
        ConditionExpressionNode $condition,
        array $item,
        array $resultItem = []
    ): bool {
        $leftValue = $this->evaluate($condition->left, $item, $resultItem);
        $rightValue = $this->evaluateConditionRight($condition->right, $item, $resultItem);
        return $condition->operator->evaluate($leftValue, $rightValue);
    }

    /**
     * Evaluates a logical group (AND/OR/XOR chain with optional nested groups) and
     * returns the combined boolean result. Empty group returns `true`.
     */
    /**
     * @param array<int|string, mixed> $item
     * @param array<int|string, mixed> $resultItem
     */
    public function evaluateGroup(ConditionGroupNode $group, array $item, array $resultItem = []): bool
    {
        $result = null;
        foreach ($group->entries as $entry) {
            $condition = $entry['condition'];
            $current = $condition instanceof ConditionGroupNode
                ? $this->evaluateGroup($condition, $item, $resultItem)
                : $this->evaluateCondition($condition, $item, $resultItem);
            $result = $entry['logical']->evaluate($result, $current);
        }
        return $result ?? true;
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Node-specific evaluators
    // ────────────────────────────────────────────────────────────────────────────────

    /**
     * @param array<int|string, mixed> $item
     * @param array<int|string, mixed> $resultItem
     */
    private function evaluateColumn(ColumnReferenceNode $node, array $item, array $resultItem): mixed
    {
        if ($this->isQuoted($node->name)) {
            return Enum\Type::matchByString($node->name);
        }
        $fromItem = $this->accessNestedValue($item, $node->name, false);
        if ($fromItem !== null) {
            return $fromItem;
        }
        $fromResult = $this->accessNestedValue($resultItem, $node->name, false);
        if ($fromResult !== null) {
            return $fromResult;
        }
        return null;
    }

    /**
     * @param array<int|string, mixed> $item
     * @param array<int|string, mixed> $resultItem
     */
    private function evaluateFunctionCall(FunctionCallNode $node, array $item, array $resultItem): mixed
    {
        if ($this->functions->isAggregate($node->name)) {
            throw new Exception\QueryLogicException(sprintf(
                'Aggregate function %s cannot be evaluated row-by-row. ' .
                'Aggregates are handled by ExpressionAggregate in the grouping phase.',
                $node->name
            ));
        }

        $evaluatedArgs = [];
        foreach ($node->arguments as $argNode) {
            $evaluatedArgs[] = $this->evaluate($argNode, $item, $resultItem);
        }
        return $this->functions->invoke($node->name, $evaluatedArgs);
    }

    /**
     * @param array<int|string, mixed> $item
     * @param array<int|string, mixed> $resultItem
     */
    private function evaluateBinaryOp(BinaryOpNode $node, array $item, array $resultItem): mixed
    {
        $left = $this->evaluate($node->left, $item, $resultItem);
        $right = $this->evaluate($node->right, $item, $resultItem);

        // SQL-style NULL propagation: any null operand yields null.
        if ($left === null || $right === null) {
            return null;
        }

        $left = $this->coerceNumeric($left);
        $right = $this->coerceNumeric($right);

        return match ($node->operator) {
            BinaryOperator::ADD => $left + $right,
            BinaryOperator::SUBTRACT => $left - $right,
            BinaryOperator::MULTIPLY => $left * $right,
            BinaryOperator::DIVIDE => $right == 0 ? null : $left / $right,
            BinaryOperator::MODULO => $right == 0 ? null : $left % $right,
        };
    }

    /**
     * @param array<int|string, mixed> $item
     * @param array<int|string, mixed> $resultItem
     */
    private function evaluateCast(CastExpressionNode $node, array $item, array $resultItem): mixed
    {
        $value = $this->evaluate($node->value, $item, $resultItem);
        return Enum\Type::castValue($value, $node->targetType);
    }

    /**
     * @param array<int|string, mixed> $item
     * @param array<int|string, mixed> $resultItem
     */
    private function evaluateCase(CaseExpressionNode $node, array $item, array $resultItem): mixed
    {
        foreach ($node->branches as $branch) {
            if ($this->evaluateGroup($branch->condition, $item, $resultItem)) {
                return $this->evaluate($branch->then, $item, $resultItem);
            }
        }
        return $node->else !== null
            ? $this->evaluate($node->else, $item, $resultItem)
            : null;
    }

    /**
     * @param array<int|string, mixed> $item
     * @param array<int|string, mixed> $resultItem
     */
    private function evaluateMatchAgainst(MatchAgainstNode $node, array $item, array $resultItem): mixed
    {
        $fieldValues = [];
        foreach ($node->fields as $fieldRef) {
            $value = $this->evaluateColumn($fieldRef, $item, $resultItem);
            $fieldValues[] = is_string($value) ? $value : (string) $value;
        }
        return Functions\String\Fulltext::execute($fieldValues, $node->searchQuery, $node->mode);
    }

    private function evaluateSubQuery(SubQueryNode $node): mixed
    {
        $key = spl_object_id($node);
        if (!array_key_exists($key, $this->subQueryCache)) {
            // Executing the subquery requires the Builder, which creates a dependency
            // cycle (Builder → Evaluator → Builder). In practice subqueries surface here
            // only for correlated cases we do not yet support; delegate to the caller
            // to handle scalar/list extraction via SubQueryExecutor in a follow-up.
            throw new Exception\QueryLogicException(
                'Subqueries inside expressions are not supported yet. ' .
                'Use JOIN (SELECT ...) instead for set-based operations.'
            );
        }
        return $this->subQueryCache[$key];
    }

    /**
     * @param ExpressionNode|Enum\Type|ExpressionNode[] $right
     * @param array<int|string, mixed> $item
     * @param array<int|string, mixed> $resultItem
     */
    private function evaluateConditionRight(
        ExpressionNode|Enum\Type|array $right,
        array $item,
        array $resultItem
    ): mixed {
        if ($right instanceof Enum\Type) {
            return $right;
        }
        if (is_array($right)) {
            $values = [];
            foreach ($right as $node) {
                $values[] = $this->evaluate($node, $item, $resultItem);
            }
            return $values;
        }
        return $this->evaluate($right, $item, $resultItem);
    }

    private function coerceNumeric(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            $parsed = Enum\Type::matchByString($value);
            if (is_int($parsed) || is_float($parsed)) {
                return $parsed;
            }
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if ($value === '' || $value === null) {
            return 0;
        }
        throw new Exception\UnexpectedValueException(
            sprintf('Cannot coerce value to numeric: %s', is_scalar($value) ? (string) $value : gettype($value))
        );
    }
}
