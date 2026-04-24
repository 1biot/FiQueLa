<?php

namespace FQL\Functions\Aggregate;

use FQL\Functions\Core\AggregateFunction;

/**
 * COUNT aggregate.
 *
 * Unlike the other aggregates, COUNT has two flavours:
 *  - `COUNT(*)`  — counts every row, null or not. The evaluator passes the row
 *    itself (typically the assoc array) as `$value`; since it is never `null`,
 *    the default accumulate path increments unconditionally.
 *  - `COUNT(expr)` — counts non-null evaluation results.
 *
 * DISTINCT is supported for expression form only.
 */
final class Count implements AggregateFunction
{
    public static function name(): string
    {
        return 'COUNT';
    }

    /**
     * @param array{distinct?: bool} $options
     * @return array{count: int, distinct: bool, seen: list<mixed>}
     */
    public static function initial(array $options = []): array
    {
        return [
            'count' => 0,
            'distinct' => (bool) ($options['distinct'] ?? false),
            'seen' => [],
        ];
    }

    /**
     * @param array{count: int, distinct: bool, seen: list<mixed>} $acc
     * @return array{count: int, distinct: bool, seen: list<mixed>}
     */
    public static function accumulate(mixed $acc, mixed $value): array
    {
        if ($value === null) {
            return $acc;
        }
        if ($acc['distinct']) {
            foreach ($acc['seen'] as $seenValue) {
                if ($seenValue === $value) {
                    return $acc;
                }
            }
            $acc['seen'][] = $value;
        }
        $acc['count']++;
        return $acc;
    }

    /**
     * @param array{count: int, distinct: bool, seen: list<mixed>} $acc
     */
    public static function finalize(mixed $acc): int
    {
        return $acc['count'];
    }
}
