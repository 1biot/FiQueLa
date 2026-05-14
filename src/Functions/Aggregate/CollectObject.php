<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Sort;
use FQL\Functions\Core\AggregateFunction;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Node\OrderByItemNode;
use FQL\Sql\Builder\ExpressionCompiler;
use FQL\Stream\ResultStreamProvider;

/**
 * `COLLECT_OBJECT(expr [AS alias], … [ORDER BY expr [ASC|DESC], …])` —
 * groups source rows into a list of objects (associative arrays) by running an
 * inner mini-SELECT and optional ORDER BY over them.
 *
 * Each group yields `array<array<string, mixed>>`. Empty group → `[]`.
 *
 * Implementation strategy: pure-lazy accumulation. The Stream pipeline hands
 * each row in as the `accumulate()` value (because the aggregate's
 * `spec.expression` is {@see \FQL\Sql\Ast\Expression\WholeRowNode}, which the
 * evaluator resolves to the source `$item`). All real work happens in
 * {@see finalize()}, which spins up a full {@see \FQL\Query\Query} pipeline
 * over a {@see ResultStreamProvider} wrapping the accumulated rows and lets
 * the existing engine handle expression evaluation, aliasing, and ordering.
 * No bespoke evaluator/sort loop here, no marker interface — `CollectObject`
 * is a perfectly ordinary `AggregateFunction`.
 */
final class CollectObject implements AggregateFunction
{
    public static function name(): string
    {
        return 'COLLECT_OBJECT';
    }

    /**
     * @param array{
     *     selectItems?: list<array{key: string, expression: ExpressionNode}>,
     *     orderings?: list<OrderByItemNode>,
     *     distinct?: bool
     * } $options
     * @return array{
     *     rows: list<array<int|string, mixed>>,
     *     selectItems: list<array{key: string, expression: ExpressionNode}>,
     *     orderings: list<OrderByItemNode>
     * }
     */
    public static function initial(array $options = []): array
    {
        return [
            'rows' => [],
            'selectItems' => $options['selectItems'] ?? [],
            'orderings' => $options['orderings'] ?? [],
        ];
    }

    /**
     * Pushes the source row into the accumulator. `$value` is the entire
     * `$item` row — the aggregate spec's `expression` is a
     * {@see \FQL\Sql\Ast\Expression\WholeRowNode}, which the evaluator
     * resolves to the row itself.
     *
     * @param array{rows: list<array<int|string, mixed>>, selectItems: list<array{key: string, expression: ExpressionNode}>, orderings: list<OrderByItemNode>} $acc
     * @return array{rows: list<array<int|string, mixed>>, selectItems: list<array{key: string, expression: ExpressionNode}>, orderings: list<OrderByItemNode>}
     */
    public static function accumulate(mixed $acc, mixed $value): array
    {
        if (is_array($value)) {
            $acc['rows'][] = $value;
        }
        return $acc;
    }

    /**
     * Builds a one-off in-memory Query over the accumulated rows, applies the
     * inner SELECT and ORDER BY, and returns the result as a plain list of
     * objects. The whole projection/ordering pipeline is reused — no custom
     * evaluator or sort code lives here.
     *
     * @param array{rows: list<array<int|string, mixed>>, selectItems: list<array{key: string, expression: ExpressionNode}>, orderings: list<OrderByItemNode>} $acc
     * @return list<array<int|string, mixed>>
     */
    public static function finalize(mixed $acc): array
    {
        if ($acc['rows'] === [] || $acc['selectItems'] === []) {
            return $acc['rows'] === [] ? [] : $acc['rows'];
        }

        $compiler = new ExpressionCompiler();
        /** @var \ArrayIterator<int, array<int|string, array<int|string, mixed>|scalar|null>> $iterator */
        $iterator = new \ArrayIterator($acc['rows']);
        $stream = new ResultStreamProvider($iterator);
        $query = $stream->query();

        foreach ($acc['selectItems'] as $item) {
            $rendered = $compiler->renderExpression($item['expression']);
            $query->select($rendered);
            if ($item['key'] !== $rendered) {
                $query->as($item['key']);
            }
        }

        foreach ($acc['orderings'] as $ord) {
            $query->orderBy($compiler->renderExpression($ord->expression));
            if ($ord->direction === Sort::DESC) {
                $query->desc();
            }
        }

        /** @var list<array<int|string, mixed>> $rows */
        $rows = iterator_to_array($query->execute()->fetchAll(), false);
        return $rows;
    }
}
