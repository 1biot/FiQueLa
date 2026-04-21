<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\AggregateFunction;

final class Min implements AggregateFunction
{
    public static function name(): string
    {
        return 'MIN';
    }

    /**
     * @param array{distinct?: bool} $options
     * @return array{min: int|float|null, hasValue: bool}
     */
    public static function initial(array $options = []): array
    {
        unset($options['distinct']); // no-op for MIN
        return [
            'min' => null,
            'hasValue' => false,
        ];
    }

    /**
     * @param array{min: int|float|null, hasValue: bool} $acc
     * @return array{min: int|float|null, hasValue: bool}
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
                sprintf('MIN value is not numeric: %s', var_export($value, true))
            );
        }
        $numeric = $value + 0;
        if (!$acc['hasValue'] || $numeric < $acc['min']) {
            $acc['min'] = $numeric;
            $acc['hasValue'] = true;
        }
        return $acc;
    }

    /**
     * @param array{min: int|float|null, hasValue: bool} $acc
     */
    public static function finalize(mixed $acc): int|float|null
    {
        return $acc['hasValue'] ? $acc['min'] : null;
    }
}
