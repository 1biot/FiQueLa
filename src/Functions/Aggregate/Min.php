<?php

namespace FQL\Functions\Aggregate;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\AggregateFunction;

/**
 * Smallest number, or earliest date, in the group.
 *
 * @phpstan-import-type Resolved from ComparableValue
 */
final class Min implements AggregateFunction
{
    public static function name(): string
    {
        return 'MIN';
    }

    /**
     * @param array{distinct?: bool} $options
     * @return array{min: Resolved|null}
     */
    public static function initial(array $options = []): array
    {
        unset($options['distinct']); // no-op for MIN
        return [
            'min' => null,
        ];
    }

    /**
     * @param array{min: Resolved|null} $acc
     * @return array{min: Resolved|null}
     * @throws UnexpectedValueException
     */
    public static function accumulate(mixed $acc, mixed $value): array
    {
        $candidate = ComparableValue::resolve($value, self::name());
        if ($candidate === null) {
            return $acc;
        }
        if ($acc['min'] === null || ComparableValue::compare($candidate, $acc['min'], self::name()) < 0) {
            $acc['min'] = $candidate;
        }
        return $acc;
    }

    /**
     * @param array{min: Resolved|null} $acc
     */
    public static function finalize(mixed $acc): mixed
    {
        return $acc['min']['value'] ?? null;
    }
}
