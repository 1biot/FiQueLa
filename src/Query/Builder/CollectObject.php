<?php

namespace FQL\Query\Builder;

use FQL\Sql;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Node\OrderByItemNode;
use FQL\Sql\Support\FieldListSplitter;
use FQL\Sql\Token\Position;
use FQL\Traits\Sortable;

/**
 * Fluent builder for the `COLLECT_OBJECT(...)` aggregate function.
 *
 * Surface is intentionally tiny: one `select(string ...)` for inner SELECT items
 * (with full `"expr AS alias"` and comma-separated multi-field syntax via
 * {@see FieldListSplitter}) and the `orderBy/asc/desc` triple inherited from
 * {@see Sortable}. Any scalar function — `ROUND`, `CONCAT`, arithmetic, … — is
 * available by writing it inside the `select()` string and letting the parser
 * handle it.
 *
 * Consumed by `Query::collectObject($builder)` which folds
 * {@see getSelectItems()} and {@see getOrderings()} into the aggregate spec.
 *
 * Example:
 * ```php
 * (new CollectObject())
 *     ->select('productId AS id, productName AS name')
 *     ->select('ROUND(price, 2) AS price')
 *     ->orderBy('price')->desc();
 * ```
 */
final class CollectObject
{
    use Sortable;

    /** @var list<array{expression: ExpressionNode, alias: string|null}> */
    private array $selectItems = [];

    public function select(string ...$fields): self
    {
        foreach (FieldListSplitter::split(...$fields) as $spec) {
            $parsed = FieldListSplitter::splitAlias($spec);
            $this->selectItems[] = [
                'expression' => Sql\Provider::parseExpression($parsed['field']),
                'alias' => $parsed['alias'],
            ];
        }
        return $this;
    }

    /**
     * Aliases the most recently added select item — mirrors the main Query's
     * `->select('foo')->as('bar')` pattern.
     */
    public function as(string $alias): self
    {
        $last = array_key_last($this->selectItems);
        if ($last === null) {
            throw new \LogicException('->as() must follow ->select()');
        }
        $this->selectItems[$last]['alias'] = $alias;
        return $this;
    }

    /** @return list<array{expression: ExpressionNode, alias: string|null}> */
    public function getSelectItems(): array
    {
        return $this->selectItems;
    }

    /**
     * Adapter for the AST consumer (`Select::collectObject`): converts
     * {@see Sortable}'s `{expression, sort}` entries into `OrderByItemNode`s
     * that {@see \FQL\Sql\Ast\Expression\CollectObjectExpressionNode} expects.
     *
     * @return list<OrderByItemNode>
     */
    public function getOrderings(): array
    {
        return array_map(
            static fn (array $entry): OrderByItemNode => new OrderByItemNode(
                $entry['expression'],
                $entry['sort'],
                Position::synthetic()
            ),
            $this->orderings
        );
    }
}
