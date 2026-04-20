<?php

namespace FQL\Sql\Builder;

use FQL\Conditions;
use FQL\Enum;
use FQL\Exception;
use FQL\Interface;
use FQL\Query;
use FQL\Sql\Ast\ExplainMode;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\CaseExpressionNode;
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
use FQL\Sql\Ast\JoinType;
use FQL\Sql\Ast\Node\JoinClauseNode;
use FQL\Sql\Ast\Node\SelectFieldNode;
use FQL\Sql\Ast\Node\SelectStatementNode;

/**
 * Walks a SelectStatementNode AST and constructs an Interface\Query via the existing
 * fluent API.
 *
 * The visitor is stateless between `build()` invocations; each call builds a fresh
 * Interface\Query from a fresh AST. Function-name dispatch is kept inline in
 * `applyFunctionCall()` to match the legacy `Sql::applyFunctionToQuery()` behaviour
 * — a future PR may extract this into a FunctionRegistry.
 */
final class QueryBuildingVisitor
{
    public function __construct(
        private readonly ExpressionCompiler $compiler,
        private readonly FileQueryResolver $fileQueryResolver
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
     * FROM is treated as an inner path navigation (`$query->from($fileQuery->query)`)
     * because the outer file stream is already open on the provided query.
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
            $fields = array_map(
                fn (ExpressionNode $n): string => $this->fieldString($n),
                $ast->groupBy->fields
            );
            $query->groupBy(...$fields);
        }

        if ($ast->having !== null) {
            $query->addHavingConditions($this->buildHavingGroup($ast->having->conditions));
        }

        if ($ast->orderBy !== null) {
            foreach ($ast->orderBy->items as $item) {
                $query->orderBy($this->fieldString($item->expression), $item->direction);
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
                'FROM clause is required for a stand-alone build(); use applyTo($existingQuery) for FROM-less SQL fragments'
            );
        }
        $source = $ast->from->source;
        if ($source instanceof SubQueryNode) {
            $inner = $this->build($source->query);
            // Subqueries used as top-level source are uncommon; the legacy parser does not
            // expose this path for top-level FROM either (only JOIN). We surface a clear
            // error rather than silently mis-building.
            return $inner;
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
            } elseif ($expression instanceof ColumnReferenceNode) {
                $query->select($expression->name);
            } elseif ($expression instanceof FunctionCallNode) {
                $this->applyFunctionCall($query, $expression);
            } elseif ($expression instanceof CastExpressionNode) {
                $query->cast($this->fieldString($expression->value), $expression->targetType);
            } elseif ($expression instanceof MatchAgainstNode) {
                $query->matchAgainst(
                    array_map(static fn (ColumnReferenceNode $f): string => $f->name, $expression->fields),
                    $expression->searchQuery,
                    $expression->mode
                );
            } elseif ($expression instanceof CaseExpressionNode) {
                $this->applyCase($query, $expression);
            } elseif ($expression instanceof LiteralNode) {
                // Rare: SELECT "literal" FROM x; treat as bare select on raw token.
                $query->select($this->compiler->renderLiteral($expression));
            } else {
                throw new Exception\QueryLogicException(
                    sprintf('Unsupported select expression: %s', get_class($expression))
                );
            }

            if ($field->alias !== null) {
                $query->as($field->alias);
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
            $target->addCondition($logical, new Conditions\SimpleCondition(
                $logical,
                $this->fieldString($condition->left),
                $condition->operator,
                $this->compiler->scalarRightValue($condition->right, $condition->operator)
            ));
        }
    }

    private function applyCase(Interface\Query $query, CaseExpressionNode $case): void
    {
        $query->case();
        foreach ($case->branches as $branch) {
            $query->whenCase(
                $this->compiler->renderConditionGroup($branch->condition),
                $this->compiler->renderExpression($branch->then)
            );
        }
        if ($case->else !== null) {
            $query->elseCase($this->compiler->renderExpression($case->else));
        }
        $query->endCase();
    }

    /**
     * Inline dispatch for function calls. Mirrors the legacy `Sql::applyFunctionToQuery()`
     * match block — a future PR extracts this into a FunctionRegistry (see CHANGELOG
     * and plan file for deferred work).
     */
    private function applyFunctionCall(Interface\Query $query, FunctionCallNode $call): void
    {
        $name = strtoupper($call->name);
        $args = $call->arguments;
        $distinct = $call->distinct;

        match ($name) {
            // Aggregate
            'AVG' => $query->avg($this->argAsString($args, 0)),
            'COUNT' => $query->count($this->argAsString($args, 0), $distinct),
            'GROUP_CONCAT' => $query->groupConcat(
                $this->argAsString($args, 0),
                $this->argAsString($args, 1, ','),
                $distinct
            ),
            'MAX' => $query->max($this->argAsString($args, 0), $distinct),
            'MIN' => $query->min($this->argAsString($args, 0), $distinct),
            'SUM' => $query->sum($this->argAsString($args, 0), $distinct),

            // Hashing
            'MD5' => $query->md5($this->argAsString($args, 0)),
            'SHA1' => $query->sha1($this->argAsString($args, 0)),

            // Math
            'CEIL' => $query->ceil($this->argAsString($args, 0)),
            'FLOOR' => $query->floor($this->argAsString($args, 0)),
            'MOD' => $query->modulo($this->argAsString($args, 0), $this->argAsInt($args, 1)),
            'ROUND' => $query->round($this->argAsString($args, 0), $this->argAsInt($args, 1)),
            'ADD' => $query->add(...$this->argsAsStrings($args)),
            'SUB' => $query->subtract(...$this->argsAsStrings($args)),
            'MULTIPLY' => $query->multiply(...$this->argsAsStrings($args)),
            'DIVIDE' => $query->divide(...$this->argsAsStrings($args)),

            // String
            'BASE64_DECODE' => $query->fromBase64($this->argAsString($args, 0)),
            'BASE64_ENCODE' => $query->toBase64($this->argAsString($args, 0)),
            'CONCAT' => $query->concat(...$this->argsAsStrings($args)),
            'CONCAT_WS' => $query->concatWithSeparator(
                $this->argAsString($args, 0),
                ...$this->argsAsStrings(array_slice($args, 1))
            ),
            'EXPLODE' => $query->explode($this->argAsString($args, 0), $this->argAsString($args, 1, ',')),
            'IMPLODE' => $query->implode($this->argAsString($args, 0), $this->argAsString($args, 1, ',')),
            'LENGTH' => $query->length($this->argAsString($args, 0)),
            'LOWER' => $query->lower($this->argAsString($args, 0)),
            'UPPER' => $query->upper($this->argAsString($args, 0)),
            'RANDOM_STRING' => $query->randomString($this->argAsInt($args, 0, 10)),
            'REPLACE' => $query->replace(
                $this->argAsString($args, 0),
                $this->argAsString($args, 1),
                $this->argAsString($args, 2)
            ),
            'REVERSE' => $query->reverse($this->argAsString($args, 0)),
            'LPAD' => $query->leftPad(
                $this->argAsString($args, 0),
                $this->argAsInt($args, 1),
                $this->argAsString($args, 2, ' ')
            ),
            'RPAD' => $query->rightPad(
                $this->argAsString($args, 0),
                $this->argAsInt($args, 1),
                $this->argAsString($args, 2, ' ')
            ),
            'SUBSTRING', 'SUBSTR' => $query->substring(
                $this->argAsString($args, 0),
                $this->argAsInt($args, 1),
                isset($args[2]) ? $this->argAsInt($args, 2) : null
            ),
            'LOCATE' => $query->locate(
                $this->argAsString($args, 0),
                $this->argAsString($args, 1),
                isset($args[2]) ? $this->argAsInt($args, 2) : null
            ),

            // Utils
            'COALESCE' => $query->coalesce(...$this->argsAsStrings($args)),
            'COALESCE_NE' => $query->coalesceNotEmpty(...$this->argsAsStrings($args)),
            'RANDOM_BYTES' => $query->randomBytes($this->argAsInt($args, 0, 10)),
            'UUID' => $query->uuid(),
            'ARRAY_COMBINE' => $query->arrayCombine($this->argAsString($args, 0), $this->argAsString($args, 1)),
            'ARRAY_MERGE' => $query->arrayMerge($this->argAsString($args, 0), $this->argAsString($args, 1)),
            'ARRAY_FILTER' => $query->arrayFilter($this->argAsString($args, 0)),
            'ARRAY_SEARCH' => $query->arraySearch($this->argAsString($args, 0), $this->argAsString($args, 1)),
            'COL_SPLIT' => $query->colSplit(
                $this->argAsString($args, 0),
                isset($args[1]) ? $this->argAsString($args, 1) : null,
                isset($args[2]) ? $this->argAsString($args, 2) : null
            ),
            'CURDATE' => $query->currentDate($this->argAsBool($args, 0)),
            'CURTIME' => $query->currentTime($this->argAsBool($args, 0)),
            'CURRENT_TIMESTAMP' => $query->currentTimestamp(),
            'NOW' => $query->now($this->argAsBool($args, 0)),
            'DATE_FORMAT' => $query->formatDate($this->argAsString($args, 0), $this->argAsString($args, 1, 'c')),
            'FROM_UNIXTIME' => $query->fromUnixTime($this->argAsString($args, 0), $this->argAsString($args, 1, 'c')),
            'STR_TO_DATE' => $query->strToDate($this->argAsString($args, 0), $this->argAsString($args, 1)),
            'DATE_DIFF' => $query->dateDiff($this->argAsString($args, 0), $this->argAsString($args, 1)),
            'DATE_ADD' => $query->dateAdd($this->argAsString($args, 0), $this->argAsString($args, 1)),
            'DATE_SUB' => $query->dateSub($this->argAsString($args, 0), $this->argAsString($args, 1)),
            'YEAR' => $query->year($this->argAsString($args, 0)),
            'MONTH' => $query->month($this->argAsString($args, 0)),
            'DAY' => $query->day($this->argAsString($args, 0)),
            'IF' => $query->if(
                $this->renderIfCondition($args[0] ?? null),
                $this->argAsString($args, 1),
                $this->argAsString($args, 2)
            ),
            'IFNULL' => $query->ifNull($this->argAsString($args, 0), $this->argAsString($args, 1)),
            'ISNULL' => $query->isNull($this->argAsString($args, 0)),
            default => throw new Exception\UnexpectedValueException("Unknown function: $name"),
        };
    }

    /**
     * Produces the textual field identifier for an expression used where the Query API
     * expects a string (GROUP BY, ORDER BY fields, SimpleCondition field, etc.).
     */
    private function fieldString(ExpressionNode $node): string
    {
        if ($node instanceof ColumnReferenceNode) {
            return $node->name;
        }
        if ($node instanceof LiteralNode) {
            return $this->compiler->renderLiteral($node);
        }
        if ($node instanceof StarNode) {
            return '*';
        }
        return $this->compiler->renderExpression($node);
    }

    private function renderIfCondition(?ExpressionNode $node): string
    {
        if ($node instanceof ConditionExpressionNode) {
            return $this->compiler->renderCondition($node);
        }
        if ($node === null) {
            return '';
        }
        return $this->compiler->renderExpression($node);
    }

    /**
     * @param ExpressionNode[] $args
     */
    private function argAsString(array $args, int $index, string $default = ''): string
    {
        if (!isset($args[$index])) {
            return $default;
        }
        $node = $args[$index];
        if ($node instanceof LiteralNode) {
            $value = $node->value;
            if (is_string($value)) {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
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
            return '';
        }
        return $this->compiler->renderExpression($node);
    }

    /**
     * @param ExpressionNode[] $args
     */
    private function argAsInt(array $args, int $index, int $default = 0): int
    {
        if (!isset($args[$index])) {
            return $default;
        }
        $node = $args[$index];
        if ($node instanceof LiteralNode) {
            return is_numeric($node->value) ? (int) $node->value : $default;
        }
        $rendered = $this->argAsString($args, $index);
        return is_numeric($rendered) ? (int) $rendered : $default;
    }

    /**
     * @param ExpressionNode[] $args
     */
    private function argAsBool(array $args, int $index, bool $default = false): bool
    {
        if (!isset($args[$index])) {
            return $default;
        }
        $node = $args[$index];
        if ($node instanceof LiteralNode) {
            if (is_bool($node->value)) {
                return $node->value;
            }
            return (bool) filter_var($node->value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        return $default;
    }

    /**
     * @param ExpressionNode[] $args
     * @return string[]
     */
    private function argsAsStrings(array $args): array
    {
        $result = [];
        foreach ($args as $i => $arg) {
            $result[] = $this->argAsString($args, $i);
        }
        return $result;
    }
}
