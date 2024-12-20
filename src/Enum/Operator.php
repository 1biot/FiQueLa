<?php

namespace JQL\Enum;

enum Operator: string
{
    case EQUAL = '=';
    case EQUAL_STRICT = '===';
    case NOT_EQUAL = '!=';
    case NOT_EQUAL_STRICT = '!==';
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUAL = '<=';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case LIKE = 'LIKE';

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
            self::LIKE => mb_strpos($value, $operand) !== false,
        };
    }
}
