<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Enum\LogicalOperator;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

/**
 * Logical group of conditions joined by AND / OR / XOR.
 *
 * Each entry is `[logical, condition]` where the first entry's `logical` is
 * by convention `LogicalOperator::AND` (it does not connect to anything).
 */
final readonly class ConditionGroupNode implements ExpressionNode, HasSpan
{
    /**
     * @param array<int, array{logical: LogicalOperator, condition: ConditionExpressionNode|ConditionGroupNode}> $entries
     */
    public function __construct(
        public array $entries,
        public Position $position,
        public ?Span $span = null
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function span(): ?Span
    {
        return $this->span;
    }

    /**
     * Copy with another `entries` (and no span: it no longer matches the source).
     *
     * @param array<int, array{logical: LogicalOperator, condition: ConditionExpressionNode|ConditionGroupNode}> $entries
     */
    public function withEntries(array $entries): self
    {
        return new self(
            $entries,
            $this->position
        );
    }

    /**
     * Copy with one more entry at the end (the logical operator is ignored for the
     * first entry).
     */
    public function withAppended(LogicalOperator $logical, ConditionExpressionNode|ConditionGroupNode $condition): self
    {
        $entries = $this->entries;
        $entries[] = ['logical' => $logical, 'condition' => $condition];
        return new self(array_values($entries), $this->position);
    }

    /**
     * Copy without the entry at `$index` (0-based); the next entry becomes first when
     * the first one is removed.
     */
    public function withoutEntry(int $index): self
    {
        if (!isset($this->entries[$index])) {
            throw new \OutOfRangeException(sprintf('Condition group has no entry %d', $index));
        }
        $entries = $this->entries;
        unset($entries[$index]);
        $entries = array_values($entries);
        if ($index === 0 && $entries !== []) {
            $entries[0]['logical'] = LogicalOperator::AND;
        }
        return new self($entries, $this->position);
    }
}
