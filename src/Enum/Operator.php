<?php

namespace FQL\Enum;

use FQL\Exception\InvalidArgumentException;

enum Operator: string
{
    use \FQL\Traits\Helpers\StringOperations;

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

    case BETWEEN = 'BETWEEN';
    case NOT_BETWEEN = 'NOT BETWEEN';

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

            self::BETWEEN => $this->evaluateBetween($left, $right),
            self::NOT_BETWEEN => !$this->evaluateBetween($left, $right),
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
            self::BETWEEN, self::NOT_BETWEEN => sprintf('%s %s %s', $value, $this->value, implode(' AND ', $right)),
            default => sprintf(
                '%s %s %s',
                $this->isBacktick($value) ? $this->removeQuotes($value) : $value,
                $this->value,
                is_string($right)
                    ? ($this->isBacktick($right) ? $right : "'$right'")
                    : ($right instanceof Type ? strtoupper($right->value) : $right)
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

    private function evaluateBetween(mixed $left, mixed $right): bool
    {
        if (!is_array($right) || count($right) !== 2) {
            throw new InvalidArgumentException(
                sprintf('BETWEEN operator requires an array with two elements, %s given', gettype($right))
            );
        }

        [$min, $max] = $right;
        if ((!is_scalar($min) && $min !== null ) || (!is_scalar($max) && $max !== null)) {
            throw new InvalidArgumentException(
                'BETWEEN operator requires both min and max values to be scalar types'
            );
        }

        if (is_numeric($left)) {
            // If all values are numeric, compare numerically
            if (is_numeric($min) && is_numeric($max)) {
                return $left >= $min && $left <= $max;
            }
        }

        // If all values are date-like, compare as dates
        if ($this->isDateLike($left)) {
            if ($this->isDateLike($min) && $this->isDateLike($max)) {
                $leftTime = strtotime($left);
                $minTime = strtotime($min);
                $maxTime = strtotime($max);

                return $leftTime >= $minTime && $leftTime <= $maxTime;
            }
        }

        // Fallback to string comparison (e.g. alphabetic ranges)
        return strcmp((string) $left, (string) $min) >= 0 &&
            strcmp((string) $left, (string) $max) <= 0;
    }
}
