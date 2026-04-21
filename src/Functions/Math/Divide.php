<?php

namespace FQL\Functions\Math;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\ScalarFunction;

final class Divide implements ScalarFunction
{
    public static function name(): string
    {
        return 'DIVIDE';
    }

    /**
     * @throws UnexpectedValueException
     */
    public static function execute(mixed ...$values): int|float
    {
        $acc = null;
        foreach ($values as $value) {
            if (is_string($value)) {
                $value = Type::matchByString($value);
            }

            if ($value === null || $value === '') {
                $value = 0;
            }

            if (!is_numeric($value) && is_string($value)) {
                throw new UnexpectedValueException(sprintf('Value is not numeric: %s', $value));
            }

            if ($acc === null) {
                $acc = $value;
            } else {
                if ($value == 0) {
                    throw new UnexpectedValueException('Division by zero.');
                }
                $acc = $acc / $value;
            }
        }
        return $acc ?? 0;
    }
}
