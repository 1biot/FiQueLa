<?php

namespace FQL\Enum;

use FQL\Exception\InvalidArgumentException;

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
    case NOT_IN = 'NOT IN';

    case LIKE = 'LIKE';
    case NOT_LIKE = 'NOT LIKE';

    case IS = 'IS';
    case NOT_IS = 'IS NOT';

    public function evaluate(mixed $left, mixed $right): bool
    {
        return match ($this) {
            self::EQUAL => $left == $right,
            self::NOT_EQUAL => $left != $right,

            self::EQUAL_STRICT => $left === $right,
            self::NOT_EQUAL_STRICT => $left !== $right,

            self::GREATER_THAN => $left > $right,
            self::GREATER_THAN_OR_EQUAL => $left >= $right,

            self::LESS_THAN => $left < $right,
            self::LESS_THAN_OR_EQUAL => $left <= $right,

            self::IN => in_array($left, $right, true),
            self::NOT_IN => !in_array($left, $right, true),

            self::LIKE => $this->evaluateLike($left, $right),
            self::NOT_LIKE => !$this->evaluateLike($left, $right),

            self::IS => $this->evaluateIs($left, $right),
            self::NOT_IS => !$this->evaluateIs($left, $right),
        };
    }

    public function render(mixed $value, mixed $right): string
    {
        return match ($this) {
            self::IN, self::NOT_IN => sprintf('%s %s (%s)', $value, $this->value, implode(
                ', ',
                array_map(
                    function ($value) {
                        return is_string($value)
                            ? sprintf('"%s"', $value)
                            : $value;
                    },
                    $right
                )
            )),
            self::LIKE, self::NOT_LIKE => sprintf('%s %s "%s"', $value, $this->value, $right),
            default => sprintf(
                '%s %s %s',
                $value,
                $this->value,
                is_string($right) ? "'$right'" : ($right instanceof Type ? strtoupper($right->value) : $right)
            ),
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

    private function evaluateIs(mixed $left, mixed $right): bool
    {
        if (!$right instanceof Type) {
            throw new InvalidArgumentException(
                sprintf(
                    'Operand must be an instance of FQL\Enum\Type instead of "%s"',
                    Type::match($right)->value
                )
            );
        }

        return match ($right) {
            Type::BOOLEAN => is_bool($left),
            Type::TRUE => $left === true,
            Type::FALSE => $left === false,
            Type::NUMBER => is_numeric($left),
            Type::INTEGER => is_integer($left),
            Type::FLOAT => is_float($left),
            Type::STRING => is_string($left),
            Type::NULL => is_null($left),
            Type::ARRAY => is_array($left),
            Type::OBJECT => is_object($left),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported type: %s', $right->value)
            )
        };
    }

    private function evaluateLike(mixed $left, mixed $right): bool
    {
        if (!is_string($left) || !is_string($right)) {
            return false;
        }

        $escaped = preg_quote($right, '/');
        $pattern = str_replace(['%', '_'], ['.*', '.'], $escaped);

        $startsWithPercent = str_starts_with($right, '%');
        if (!$startsWithPercent) {
            $pattern = '^' . $pattern;
        }

        $endsWithPercent   = str_ends_with($right, '%');
        if (!$endsWithPercent) {
            $pattern .= '$';
        }

        return (bool) preg_match('/' . $pattern . '/i', $left);
    }
}
