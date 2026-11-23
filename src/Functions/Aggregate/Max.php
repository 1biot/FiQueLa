<?php

namespace FQL\Functions\Aggregate;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\AggregateFunction;

/**
 * Largest number, or latest date, in the group.
 *
 * @phpstan-import-type Resolved from ComparableValue
 */
final class Max implements AggregateFunction
{
    public static function name(): string
    {
        return 'MAX';
    }

    /**
     * @param array{distinct?: bool} $options
     * @return array{max: Resolved|null}
     */
    public static function initial(array $options = []): array
    {
        // DISTINCT is a no-op for MAX — retained in options for API parity
        // but intentionally ignored.
        unset($options['distinct']);
        return [
            'max' => null,
        ];
    }

    /**
     * @param array{max: Resolved|null} $acc
     * @return array{max: Resolved|null}
     * @throws UnexpectedValueException
     */
    public static function accumulate(mixed $acc, mixed $value): array
    {
        $candidate = ComparableValue::resolve($value, self::name());
        if ($candidate === null) {
            return $acc;
        }
        if ($acc['max'] === null || ComparableValue::compare($candidate, $acc['max'], self::name()) > 0) {
            $acc['max'] = $candidate;
        }
        return $acc;
    }

    /**
     * @param array{max: Resolved|null} $acc
     */
    public static function finalize(mixed $acc): mixed
    {
        return $acc['max']['value'] ?? null;
    }
}
