<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Functions\Core\AggregateFunction;

/**
 * `GROUP_CONCAT(expr [, separator])` — joins all non-null group values into a
 * single string using the provided separator (default `,`).
 *
 * The separator is carried on the accumulator so each aggregate invocation
 * within the same grouping phase retains its configured joiner.
 */
final class GroupConcat implements AggregateFunction
{
    public static function name(): string
    {
        return 'GROUP_CONCAT';
    }

    /**
     * @param array{distinct?: bool, separator?: string} $options
     * @return array{parts: list<string>, distinct: bool, seen: list<mixed>, separator: string}
     */
    public static function initial(array $options = []): array
    {
        return [
            'parts' => [],
            'distinct' => (bool) ($options['distinct'] ?? false),
            'seen' => [],
            'separator' => (string) ($options['separator'] ?? ','),
        ];
    }

    /**
     * @param array{parts: list<string>, distinct: bool, seen: list<mixed>, separator: string} $acc
     * @return array{parts: list<string>, distinct: bool, seen: list<mixed>, separator: string}
     */
    public static function accumulate(mixed $acc, mixed $value): array
    {
        if ($value === null) {
            return $acc;
        }
        if (is_string($value)) {
            $value = Type::matchByString($value);
        }
        if ($acc['distinct']) {
            foreach ($acc['seen'] as $seenValue) {
                if ($seenValue === $value) {
                    return $acc;
                }
            }
            $acc['seen'][] = $value;
        }
        $acc['parts'][] = is_scalar($value) ? (string) $value : (string) json_encode($value);
        return $acc;
    }

    /**
     * @param array{parts: list<string>, distinct: bool, seen: list<mixed>, separator: string} $acc
     */
    public static function finalize(mixed $acc): string
    {
        return implode($acc['separator'], $acc['parts']);
    }
}
