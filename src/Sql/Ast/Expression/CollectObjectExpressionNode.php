<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Ast\Node\OrderByItemNode;
use FQL\Sql\Token\Position;

/**
 * AST envelope carrying the inner SELECT items and optional ORDER BY of a
 * `COLLECT_OBJECT(...)` call. Wrapped as the single argument of a
 * `FunctionCallNode('COLLECT_OBJECT', [...])`; the Select trait's
 * `storeAggregate()` extracts the contents into the aggregate spec options.
 *
 * Note: this node has no runtime evaluation semantics — it is purely a
 * build-time carrier between the parser/fluent builder and `storeAggregate`.
 * Attempting to evaluate it via `ExpressionEvaluator` is a logic error.
 */
final readonly class CollectObjectExpressionNode implements ExpressionNode
{
    /**
     * @param list<array{expression: ExpressionNode, alias: string|null}> $selectItems
     * @param list<OrderByItemNode> $orderings
     */
    public function __construct(
        public array $selectItems,
        public array $orderings,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
