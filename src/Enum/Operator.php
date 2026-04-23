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
    case REGEXP = 'REGEXP';
    case NOT_REGEXP = 'NOT REGEXP';

    case IS = 'IS';
    case NOT_IS = 'IS NOT';

    case BETWEEN = 'BETWEEN';
    case NOT_BETWEEN = 'NOT BETWEEN';

    public function evaluate(mixed $left, mixed $right): bool
    {
        return match ($this) {
            // Loose-equal ops coerce string operands through Type::matchByString
            // so raw CSV/XML strings (e.g. "100") compare equal to their
            // parsed counterparts (int 100) without forcing eager per-cell
            // coercion during row iteration.
            self::EQUAL => self::coerceValue($left) == self::coerceValue($right),
            self::NOT_EQUAL => self::coerceValue($left) != self::coerceValue($right),

            // Strict ops intentionally skip coercion — users writing `===`
            // are opting into exact-type matching.
            self::EQUAL_STRICT => $left === $right,
            self::NOT_EQUAL_STRICT => $left !== $right,

            self::GREATER_THAN => self::coerceValue($left) > self::coerceValue($right),
            self::GREATER_THAN_OR_EQUAL => self::coerceValue($left) >= self::coerceValue($right),
            self::LESS_THAN => self::coerceValue($left) < self::coerceValue($right),
            self::LESS_THAN_OR_EQUAL => self::coerceValue($left) <= self::coerceValue($right),

            self::IN => $this->evaluateIn($left, $right),
            self::NOT_IN => !$this->evaluateIn($left, $right),

            self::LIKE => $this->evaluateLike($left, $right),
            self::NOT_LIKE => !$this->evaluateLike($left, $right),
            self::REGEXP => $this->evaluateRegexp($left, $right),
            self::NOT_REGEXP => !$this->evaluateRegexp($left, $right),

            self::IS => $this->evaluateIs($left, $right),
            self::NOT_IS => !$this->evaluateIs($left, $right),

            self::BETWEEN => $this->evaluateBetween($left, $right),
            self::NOT_BETWEEN => !$this->evaluateBetween($left, $right),
        };
    }

    /**
     * Per-value type coercion hook. Pure strings go through
     * {@see Type::matchByString()} (empty string maps to null so `IS NULL`
     * keeps matching empty CSV cells that used to be eager-typed); other
     * types are passed through. Used by the operator evaluator to bridge the
     * gap between stream providers that yield raw strings (CSV, XML) and
     * comparison operators that expect native PHP types.
     */
    private static function coerceValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        if ($value === '') {
            return null;
        }
        return Type::matchByString($value);
    }

    public function render(mixed $value, mixed $right): string
    {
        return match ($this) {
            self::IN, self::NOT_IN => sprintf('%s %s (%s)', $value, $this->value, implode(
                ', ',
                array_map(
                    function ($value) {
                        return is_string($value)
                            ? ($this->hasSquareBracketsString($value)
                                ? $value
                                : sprintf('"%s"', $value)
                            ) : $value;
                    },
                    is_string($right) ? [$right] : $right
                )
            )),
            self::LIKE, self::NOT_LIKE, self::REGEXP, self::NOT_REGEXP => sprintf('%s %s "%s"', $value, $this->value, $right),
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

        // Type predicates are permissive: `$left IS INTEGER` is true if the
        // raw value already is an int OR if it's a string that parses as an
        // int (so CSV cell "42" matches). This preserves the original PHP
        // `is_*` semantics for native types (e.g. `'0' IS STRING` stays true)
        // while unlocking the CSV/XML use case where stream providers yield
        // raw strings and expect `IS NUMBER`/`IS INTEGER` to still work.
        return match ($right) {
            Type::BOOLEAN => is_bool($left) || $this->stringMatches($left, 'is_bool'),
            Type::TRUE => $left === true || (is_string($left) && self::coerceValue($left) === true),
            Type::FALSE => $left === false || (is_string($left) && self::coerceValue($left) === false),
            Type::NUMBER => is_numeric($left),
            Type::INTEGER => is_integer($left) || $this->stringMatches($left, 'is_integer'),
            Type::FLOAT => is_float($left) || $this->stringMatches($left, 'is_float'),
            Type::STRING => is_string($left),
            // Empty strings are treated as null so CSV empty cells match IS NULL
            // — mirrors the behaviour of the old eager-typed row iteration.
            Type::NULL => $left === null || $left === '',
            Type::ARRAY => is_array($left),
            Type::OBJECT => is_object($left),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported type: %s', $right->value)
            )
        };
    }

    /**
     * Returns true when `$value` is a string whose coerced form satisfies the
     * given PHP type predicate (`is_int`, `is_bool`, …). Used by IS checks to
     * extend native type recognition onto raw string operands.
     *
     * @param callable(mixed): bool $predicate
     */
    private function stringMatches(mixed $value, callable $predicate): bool
    {
        return is_string($value) && $value !== '' && $predicate(self::coerceValue($value));
    }

    /**
     * IN / NOT IN with coerced strict compare. Needed because the raw
     * `in_array($left, $right, true)` mismatches across type boundaries —
     * string "100" would never match int 100 coming from a parsed literal.
     *
     * @param mixed $left
     * @param array<int, mixed> $right
     */
    private function evaluateIn(mixed $left, array $right): bool
    {
        $leftCoerced = self::coerceValue($left);
        foreach ($right as $candidate) {
            if ($leftCoerced === self::coerceValue($candidate)) {
                return true;
            }
        }
        return false;
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

    private function evaluateRegexp(mixed $left, mixed $right): bool
    {
        if (!is_string($left) || !is_string($right)) {
            return false;
        }

        $pattern = preg_match('/^(.).+\1[imsxuADSUXJu]*$/', $right) === 1
            ? $right
            : '/' . str_replace('/', '\/', $right) . '/';

        $result = @preg_match($pattern, $left);
        if ($result === false) {
            return false;
        }

        return $result === 1;
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
                $leftTime = strtotime((string) $left);
                $minTime = strtotime((string) $min);
                $maxTime = strtotime((string) $max);

                return $leftTime >= $minTime && $leftTime <= $maxTime;
            }
        }

        // Fallback to string comparison (e.g. alphabetic ranges)
        return strcmp((string) $left, (string) $min) >= 0 &&
            strcmp((string) $left, (string) $max) <= 0;
    }
}
