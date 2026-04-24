<?php

namespace FQL\Traits;

use FQL\Exception;
use FQL\Sql;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Builder\ExpressionCompiler;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Token\Position;

trait Groupable
{
    /**
     * GROUP BY key list. Each entry is an `ExpressionNode` produced by
     * `Sql\Provider::parseExpression()` — fluent `groupBy('year(date)')` and
     * SQL `GROUP BY year(date)` converge on the same AST. Runtime evaluation
     * is handled by `Results\Stream::createGroupKey()`.
     *
     * @var ExpressionNode[] $groupByFields
     */
    private array $groupByFields = [];
    private bool $groupableBlocked = false;

    public function blockGroupable(): void
    {
        $this->groupableBlocked = true;
    }

    public function isGroupableEmpty(): bool
    {
        return $this->groupByFields === [];
    }

    public function groupBy(string ...$fields): self
    {
        if ($this->groupableBlocked) {
            throw new Exception\QueryLogicException('GROUP BY is not allowed in DESCRIBE mode');
        }

        foreach ($fields as $field) {
            $this->groupByFields[] = self::parseOrColumn($field);
        }
        return $this;
    }

    /**
     * Parses `$field` as an SQL expression but falls back to a bare
     * `ColumnReferenceNode` when the input is a plain identifier that happens
     * to collide with an SQL keyword (`group`, `order`, `select`, …). This
     * mirrors the permissive behaviour of the legacy fluent API where any
     * string was treated as a column path.
     */
    private static function parseOrColumn(string $field): ExpressionNode
    {
        try {
            return Sql\Provider::parseExpression($field);
        } catch (ParseException) {
            return new ColumnReferenceNode($field, Position::synthetic());
        }
    }

    public function groupByToString(): string
    {
        if ($this->groupByFields === []) {
            return '';
        }
        $compiler = new ExpressionCompiler();
        $parts = [];
        foreach ($this->groupByFields as $field) {
            $parts[] = $compiler->renderExpression($field);
        }
        return PHP_EOL . sprintf('GROUP BY %s', implode(', ', $parts));
    }
}
