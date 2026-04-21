<?php

namespace FQL\Sql\Builder;

use FQL\Conditions;
use FQL\Enum;
use FQL\Exception;
use FQL\Interface;
use FQL\Query;
use FQL\Sql\Ast\ExplainMode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\JoinType;
use FQL\Sql\Ast\Node\JoinClauseNode;
use FQL\Sql\Ast\Node\SelectFieldNode;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Runtime\ExpressionEvaluator;

/**
 * Walks a `SelectStatementNode` AST and constructs an `Interface\Query` via the
 * fluent API, wiring the runtime expression evaluator into every non-trivial
 * SELECT / WHERE / HAVING / GROUP BY / ORDER BY clause.
 *
 * Design highlights:
 *  - Every SELECT / GROUP BY / ORDER BY clause is **stringified** via
 *    {@see ExpressionCompiler} and fed back into the fluent API (`select()`,
 *    `groupBy()`, `orderBy()`). That fluent entry point parses the string
 *    through `Sql\Provider::parseExpression()`, so the SQL and fluent paths
 *    converge on the same AST construction code — no `@internal` side door.
 *  - `SELECT *` and wildcard expansions retain their bespoke handling in
 *    `Traits\Select` (they can't be parsed as plain expressions).
 *  - **WHERE / HAVING** conditions still build {@see Conditions\ExpressionCondition}
 *    directly from the AST so complex operands resolve at runtime via the
 *    evaluator without round-tripping through a string.
 *
 * Cost of the stringify-reparse hop: roughly 50 µs per clause, paid once at
 * build time. Negligible vs. any serialisation / stream setup.
 */
final class QueryBuildingVisitor
{
    public function __construct(
        private readonly ExpressionCompiler $compiler,
        private readonly FileQueryResolver $fileQueryResolver,
        private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator()
    ) {
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function build(SelectStatementNode $ast): Interface\Query
    {
        return $this->buildInternal($ast, null);
    }

    /**
     * Applies the parsed AST to an existing Interface\Query instance rather than opening
     * a new stream from the FROM clause. Used by consumers who already hold a Query
     * (typically a stream->query() result) and want to extend it with SQL clauses.
     *
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function applyTo(SelectStatementNode $ast, Interface\Query $query): Interface\Query
    {
        return $this->buildInternal($ast, $query);
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    private function buildInternal(SelectStatementNode $ast, ?Interface\Query $override): Interface\Query
    {
        $query = $override !== null
            ? $this->applyFromOntoExisting($ast, $override)
            : $this->buildSource($ast);

        if ($ast->describe) {
            return $query->describe();
        }

        if ($ast->explain === ExplainMode::EXPLAIN) {
            $query->explain();
        } elseif ($ast->explain === ExplainMode::EXPLAIN_ANALYZE) {
            $query->explainAnalyze();
        }

        if ($ast->distinct) {
            $query->distinct();
        }

        $this->applyFields($query, $ast->fields);

        foreach ($ast->joins as $join) {
            $this->applyJoin($query, $join);
        }

        if ($ast->where !== null) {
            $query->addWhereConditions($this->buildWhereGroup($ast->where->conditions));
        }

        if ($ast->groupBy !== null) {
            foreach ($ast->groupBy->fields as $field) {
                $query->groupBy($this->compiler->renderExpression($field));
            }
        }

        if ($ast->having !== null) {
            $query->addHavingConditions($this->buildHavingGroup($ast->having->conditions));
        }

        if ($ast->orderBy !== null) {
            foreach ($ast->orderBy->items as $item) {
                $query->orderBy(
                    $this->compiler->renderExpression($item->expression),
                    $item->direction
                );
            }
        }

        if ($ast->limit !== null) {
            if ($ast->limit->limit > 0) {
                $query->limit($ast->limit->limit, $ast->limit->offset);
            } elseif ($ast->limit->offset !== null) {
                $query->offset($ast->limit->offset);
            }
        }

        if ($ast->into !== null) {
            $fileQuery = $this->fileQueryResolver->resolve($ast->into->target->fileQuery, mustExist: false);
            $query->into($fileQuery);
        }

        foreach ($ast->unions as $union) {
            $rhs = $this->build($union->query);
            $union->all ? $query->unionAll($rhs) : $query->union($rhs);
        }

        return $query;
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    private function buildSource(SelectStatementNode $ast): Interface\Query
    {
        if ($ast->from === null) {
            throw new Exception\QueryLogicException(
                'FROM clause is required for a stand-alone build();'
                . ' use applyTo($existingQuery) for FROM-less SQL fragments'
            );
        }
        $source = $ast->from->source;
        if ($source instanceof SubQueryNode) {
            return $this->build($source->query);
        }
        if (!$source instanceof FileQueryNode) {
            throw new Exception\QueryLogicException('FROM source must be a FileQuery or subquery');
        }
        $fileQuery = $this->fileQueryResolver->resolve($source->fileQuery, mustExist: true);
        $query = Query\Provider::fromFileQuery((string) $fileQuery);
        if ($ast->from->alias !== null) {
            $query->as($ast->from->alias);
        }
        return $query;
    }

    /**
     * When applying to an existing Query, FROM navigates deeper into the already-open
     * source rather than opening a new stream.
     */
    private function applyFromOntoExisting(SelectStatementNode $ast, Interface\Query $query): Interface\Query
    {
        if ($ast->from === null) {
            return $query;
        }
        $source = $ast->from->source;
        if ($source instanceof FileQueryNode && $source->fileQuery->query !== null) {
            $query->from($source->fileQuery->query);
        }
        if ($ast->from->alias !== null) {
            $query->as($ast->from->alias);
        }
        return $query;
    }

    /**
     * @param SelectFieldNode[] $fields
     * @throws Exception\SelectException
     */
    private function applyFields(Interface\Query $query, array $fields): void
    {
        foreach ($fields as $field) {
            if ($field->excluded) {
                $query->exclude($this->fieldString($field->expression));
                continue;
            }

            $expression = $field->expression;

            if ($expression instanceof StarNode) {
                $query->selectAll();
                if ($field->alias !== null) {
                    $query->as($field->alias);
                }
                continue;
            }

            // Stringify the AST and hand it to the fluent select() — that path
            // now parses SQL expression strings into AST internally, so there's
            // a single entry point for both the SQL builder and user fluent code.
            $rendered = $this->compiler->renderExpression($expression);
            if ($field->alias !== null) {
                $query->select($rendered . ' ' . Interface\Query::AS . ' ' . $field->alias);
            } else {
                $query->select($rendered);
            }
        }
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    private function applyJoin(Interface\Query $query, JoinClauseNode $join): void
    {
        $joinSource = $this->resolveJoinSource($join->source);

        match ($join->type) {
            JoinType::INNER => $query->innerJoin($joinSource, $join->alias),
            JoinType::LEFT => $query->leftJoin($joinSource, $join->alias),
            JoinType::RIGHT => $query->rightJoin($joinSource, $join->alias),
            JoinType::FULL => $query->fullJoin($joinSource, $join->alias),
        };

        // JOIN ON condition: field op field-or-value
        $condition = $join->on;
        $leftField = $this->fieldString($condition->left);
        $rightValue = $this->compiler->scalarRightValue($condition->right, $condition->operator);
        if (is_array($rightValue) || $rightValue instanceof Enum\Type) {
            throw new Exception\QueryLogicException('JOIN ON condition does not support IN/BETWEEN/IS');
        }
        $query->on($leftField, $condition->operator, (string) $rightValue);
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    private function resolveJoinSource(ExpressionNode $node): Interface\Query
    {
        if ($node instanceof SubQueryNode) {
            return $this->build($node->query);
        }
        if ($node instanceof FileQueryNode) {
            $fileQuery = $this->fileQueryResolver->resolve($node->fileQuery, mustExist: true);
            return Query\Provider::fromFileQuery((string) $fileQuery);
        }
        throw new Exception\QueryLogicException(
            sprintf('Unsupported JOIN source: %s', get_class($node))
        );
    }

    private function buildWhereGroup(ConditionGroupNode $node): Conditions\WhereConditionGroup
    {
        $root = new Conditions\WhereConditionGroup();
        $this->populateConditionGroup($root, $node);
        return $root;
    }

    private function buildHavingGroup(ConditionGroupNode $node): Conditions\HavingConditionGroup
    {
        $root = new Conditions\HavingConditionGroup();
        $this->populateConditionGroup($root, $node);
        return $root;
    }

    private function populateConditionGroup(
        Conditions\GroupCondition $target,
        ConditionGroupNode $astGroup
    ): void {
        foreach ($astGroup->entries as $entry) {
            $logical = $entry['logical'];
            $condition = $entry['condition'];
            if ($condition instanceof ConditionGroupNode) {
                $nested = new Conditions\GroupCondition($logical, $target);
                $target->addCondition($logical, $nested);
                $this->populateConditionGroup($nested, $condition);
                continue;
            }
            // Always build ExpressionCondition so complex operands (function calls,
            // arithmetic) resolve at runtime via the evaluator. Plain-column
            // equality still works: evaluator reduces ColumnReferenceNode → value.
            $target->addCondition($logical, new Conditions\ExpressionCondition(
                $logical,
                $condition->left,
                $condition->operator,
                $condition->right,
                $this->evaluator
            ));
        }
    }

    /**
     * Produces the textual field identifier for an expression used where the Query API
     * expects a string (JOIN ON field, excluded field names).
     */
    private function fieldString(ExpressionNode $node): string
    {
        if ($node instanceof ColumnReferenceNode) {
            return $node->name;
        }
        return $this->compiler->renderExpression($node);
    }
}
