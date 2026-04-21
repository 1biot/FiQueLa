<?php

namespace FQL\Traits;

use FQL\Enum;
use FQL\Exception;
use FQL\Interface\Query;
use FQL\Sql;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Builder\ExpressionCompiler;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Token\Position;

trait Sortable
{
    /**
     * Unified ORDER BY list. Each entry carries the expression to evaluate for
     * the sort key and the chosen direction. Entries always hold an
     * `ExpressionNode`; fluent `orderBy('length(name)')` parses the string
     * through `Sql\Provider::parseExpression()`, so both the fluent API and the
     * SQL builder converge on a single code path.
     *
     * @var array<int, array{expression: ExpressionNode, sort: Enum\Sort}> $orderings
     */
    private array $orderings = [];

    private bool $sortableBlocked = false;

    public function blockSortable(): void
    {
        $this->sortableBlocked = true;
    }

    public function isSortableEmpty(): bool
    {
        return $this->orderings === [];
    }

    public function sortBy(string $field, ?Enum\Sort $type = null): Query
    {
        if ($this->sortableBlocked) {
            throw new Exception\QueryLogicException('ORDER BY is not allowed in DESCRIBE mode');
        }

        try {
            $expression = Sql\Provider::parseExpression($field);
        } catch (ParseException) {
            // Plain identifier that collides with a SQL keyword (e.g. `group`).
            // Preserve the legacy fluent API behaviour where any string is a
            // column path by default.
            $expression = new ColumnReferenceNode($field, Position::synthetic());
        }

        // Preserve the "field already used for sorting" diagnostic for plain
        // column references — that's the only case where duplication is
        // unambiguous. Expression orderings are idempotent enough to allow.
        if ($expression instanceof ColumnReferenceNode) {
            foreach ($this->orderings as $entry) {
                $existing = $entry['expression'];
                if ($existing instanceof ColumnReferenceNode && $existing->name === $expression->name) {
                    throw new Exception\OrderByException(
                        sprintf('Field "%s" is already used for sorting.', $expression->name)
                    );
                }
            }
        }

        $this->orderings[] = [
            'expression' => $expression,
            'sort' => $type ?? Enum\Sort::ASC,
        ];
        return $this;
    }

    public function orderBy(string $field, ?Enum\Sort $type = null): Query
    {
        return $this->sortBy($field, $type);
    }

    public function asc(): Query
    {
        return $this->setLastSortType(Enum\Sort::ASC);
    }

    public function desc(): Query
    {
        return $this->setLastSortType(Enum\Sort::DESC);
    }

    public function clearOrderings(): Query
    {
        $this->orderings = [];
        return $this;
    }

    private function orderByToString(): string
    {
        if ($this->orderings === []) {
            return '';
        }

        $compiler = new ExpressionCompiler();
        $parts = [];
        foreach ($this->orderings as $entry) {
            $parts[] = sprintf(
                '%s %s',
                $compiler->renderExpression($entry['expression']),
                trim(strtoupper($entry['sort']->value))
            );
        }

        return PHP_EOL . sprintf('ORDER BY %s', implode(', ', $parts));
    }

    private function setLastSortType(Enum\Sort $type): Query
    {
        $lastIndex = array_key_last($this->orderings);
        if ($lastIndex === null) {
            throw new Exception\OrderByException(
                sprintf('No field available to set sorting type "%s".', $type->value)
            );
        }

        $this->orderings[$lastIndex]['sort'] = $type;
        return $this;
    }
}
