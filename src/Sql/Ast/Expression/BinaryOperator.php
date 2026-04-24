<?php

namespace FQL\Sql\Ast\Expression;

enum BinaryOperator: string
{
    case ADD = '+';
    case SUBTRACT = '-';
    case MULTIPLY = '*';
    case DIVIDE = '/';
    case MODULO = '%';

    /**
     * Pratt parser operator precedence (higher binds tighter).
     * Matches standard SQL / C family: `*`, `/`, `%` bind tighter than `+`, `-`.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::ADD, self::SUBTRACT => 10,
            self::MULTIPLY, self::DIVIDE, self::MODULO => 20,
        };
    }

    /**
     * @return string Name of the corresponding Interface\Query method
     *                (see FunctionHandler mapping in MathHandlers).
     */
    public function functionName(): string
    {
        return match ($this) {
            self::ADD => 'ADD',
            self::SUBTRACT => 'SUB',
            self::MULTIPLY => 'MULTIPLY',
            self::DIVIDE => 'DIVIDE',
            self::MODULO => 'MOD',
        };
    }
}
