<?php

namespace FQL\Results;

use FQL\Enum;

/**
 * Fixed-capacity max-heap used for Top-N sorting (`ORDER BY ... LIMIT`).
 *
 * Rows are ordered by their pre-evaluated ORDER BY sort keys. The row that
 * would sort *last* sits at the root, so once the heap is full every further
 * row evicts the current worst one in O(log n) — capping memory at the
 * configured capacity regardless of how many rows are offered. Ties are broken
 * by insertion order, so the drained result is identical to the stable
 * {@see usort()} path used for unbounded sorts.
 *
 * @phpstan-import-type StreamProviderArrayIteratorValue from Stream
 * @phpstan-type HeapEntry array{
 *     keys: array<int, mixed>,
 *     seq: int,
 *     item: array<int|string, array<int|string, mixed>|scalar|null>
 * }
 * @extends \SplHeap<HeapEntry>
 */
final class BoundedSortHeap extends \SplHeap
{
    private int $seq = 0;

    /**
     * @param array<int, Enum\Sort> $directions Sort direction per ORDER BY key.
     * @param int $capacity Maximum number of rows to retain (`offset + limit`).
     */
    public function __construct(
        private readonly array $directions,
        private readonly int $capacity,
    ) {
    }

    /**
     * Offers a row to the heap, evicting the worst retained row when full.
     *
     * @param array<int, mixed> $keys Pre-evaluated ORDER BY key values.
     * @param StreamProviderArrayIteratorValue $item
     */
    public function offer(array $keys, array $item): void
    {
        $this->insert(['keys' => $keys, 'seq' => $this->seq++, 'item' => $item]);
        if (count($this) > $this->capacity) {
            $this->extract();
        }
    }

    /**
     * Returns the retained rows in ascending ORDER BY order.
     *
     * Draining an {@see \SplHeap} yields greatest-first (the row that sorts
     * last), so the buffer is reversed to recover the final order. This
     * consumes the heap.
     *
     * @return list<StreamProviderArrayIteratorValue>
     */
    public function sorted(): array
    {
        $ordered = [];
        foreach ($this as $entry) {
            $ordered[] = $entry['item'];
        }

        return array_reverse($ordered);
    }

    /**
     * @param HeapEntry $value1
     * @param HeapEntry $value2
     */
    protected function compare(mixed $value1, mixed $value2): int
    {
        foreach ($this->directions as $i => $direction) {
            $cmp = $direction === Enum\Sort::ASC
                ? ($value1['keys'][$i] <=> $value2['keys'][$i])
                : ($value2['keys'][$i] <=> $value1['keys'][$i]);
            if ($cmp !== 0) {
                // Positive => $value1 sorts after $value2, so it carries the
                // higher extraction priority and is the first to be evicted
                // once the heap is over capacity.
                return $cmp;
            }
        }

        // Stable tie-break: a later row sorts "after" an earlier one, so it is
        // evicted first and the earlier row is retained.
        return $value1['seq'] <=> $value2['seq'];
    }
}
