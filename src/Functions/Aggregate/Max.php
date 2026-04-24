<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\AggregateFunction;

final class Max implements AggregateFunction
{
    public static function name(): string
    {
        return 'MAX';
    }

    /**
     * @param array{distinct?: bool} $options
     * @return array{max: int|float|null, hasValue: bool}
     */
    public static function initial(array $options = []): array
    {
        // DISTINCT is a no-op for MAX — retained in options for API parity
        // but intentionally ignored.
        unset($options['distinct']);
        return [
            'max' => null,
            'hasValue' => false,
        ];
    }

    /**
     * @param array{max: int|float|null, hasValue: bool} $acc
     * @return array{max: int|float|null, hasValue: bool}
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
                sprintf('MAX value is not numeric: %s', var_export($value, true))
            );
        }
        $numeric = $value + 0;
        if (!$acc['hasValue'] || $numeric > $acc['max']) {
            $acc['max'] = $numeric;
            $acc['hasValue'] = true;
        }
        return $acc;
    }

    /**
     * @param array{max: int|float|null, hasValue: bool} $acc
     */
    public static function finalize(mixed $acc): int|float|null
    {
        return $acc['hasValue'] ? $acc['max'] : null;
    }
}
