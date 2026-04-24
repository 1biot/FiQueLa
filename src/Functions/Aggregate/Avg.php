<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\AggregateFunction;

final class Avg implements AggregateFunction
{
    public static function name(): string
    {
        return 'AVG';
    }

    /**
     * @param array{distinct?: bool} $options
     * @return array{sum: int|float, count: int, distinct: bool, seen: list<mixed>}
     */
    public static function initial(array $options = []): array
    {
        return [
            'sum' => 0,
            'count' => 0,
            'distinct' => (bool) ($options['distinct'] ?? false),
            'seen' => [],
        ];
    }

    /**
     * @param array{sum: int|float, count: int, distinct: bool, seen: list<mixed>} $acc
     * @return array{sum: int|float, count: int, distinct: bool, seen: list<mixed>}
     * @throws UnexpectedValueException
     */
    public static function accumulate(mixed $acc, mixed $value): array
    {
        if ($value === null) {
            return $acc;
        }
        if (is_string($value)) {
            $value = Type::matchByString($value);
        }
        if (!is_numeric($value)) {
            throw new UnexpectedValueException(
                sprintf('AVG value is not numeric: %s', var_export($value, true))
            );
        }
        if ($acc['distinct']) {
            foreach ($acc['seen'] as $seenValue) {
                if ($seenValue === $value) {
                    return $acc;
                }
            }
            $acc['seen'][] = $value;
        }
        $acc['sum'] += $value;
        $acc['count']++;
        return $acc;
    }

    /**
     * @param array{sum: int|float, count: int, distinct: bool, seen: list<mixed>} $acc
     */
    public static function finalize(mixed $acc): int|float
    {
        if ($acc['count'] === 0) {
            return 0;
        }
        return $acc['sum'] / $acc['count'];
    }
}
