<?php

namespace FQL\Enum;

enum LogicalOperator: string
{
    case AND = 'AND';
    case OR = 'OR';
    case XOR = 'XOR';

    public function evaluate(?bool $left, bool $right): bool
    {
        return match ($this) {
            self::AND => $left === null ? $right : $left && $right,
            self::OR => $left === null ? $right : $left || $right,
            self::XOR => $left === null ? $right : $left xor $right,
        };
    }

    public function render(bool $spaces = false): string
    {
        return sprintf($spaces ? ' %s ' : '%s', $this->value);
    }
}
