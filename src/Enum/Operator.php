<?php

namespace UQL\Enum;

use UQL\Exceptions\InvalidArgumentException;

enum Operator: string
{
    case EQUAL = '=';
    case EQUAL_STRICT = '==';
    case NOT_EQUAL = '!=';
    case NOT_EQUAL_STRICT = '!==';
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUAL = '<=';
    case IN = 'IN';
    case NOT_IN = 'NOT_IN';
    case CONTAINS = 'CONTAINS';
    case STARTS_WITH = 'STARTS_WITH';
    case ENDS_WITH = 'ENDS_WITH';

    public function evaluate(mixed $value, mixed $operand): bool
    {
        return match ($this) {
            self::EQUAL => $value == $operand,
            self::EQUAL_STRICT => $value === $operand,
            self::NOT_EQUAL => $value != $operand,
            self::NOT_EQUAL_STRICT => $value !== $operand,
            self::GREATER_THAN => $value > $operand,
            self::GREATER_THAN_OR_EQUAL => $value >= $operand,
            self::LESS_THAN => $value < $operand,
            self::LESS_THAN_OR_EQUAL => $value <= $operand,
            self::IN => in_array($value, $operand, true),
            self::NOT_IN => !in_array($value, $operand, true),
            self::CONTAINS => str_contains($value, $operand),
            self::STARTS_WITH => str_starts_with($value, $operand),
            self::ENDS_WITH => str_ends_with($value, $operand),
        };
    }

    /**
     * Get the operator or throw an exception if it's invalid
     *
     * @param string $operator
     * @return self
     */
    public static function fromOrFail(string $operator): self
    {
        return self::tryFrom($operator)
            ?? throw new InvalidArgumentException(sprintf('Unsupported operator: %s', $operator));
    }
}
