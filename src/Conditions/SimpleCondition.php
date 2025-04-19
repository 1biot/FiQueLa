<?php

namespace FQL\Conditions;

use FQL\Enum;
use FQL\Exception\UnexpectedValueException;
use FQL\Interface;
use FQL\Traits\Helpers;

/**
 * @phpstan-import-type ConditionValue from Interface\Query
 */
class SimpleCondition extends Condition
{
    use Helpers\NestedArrayAccessor;

    /**
     * @param ConditionValue $value
     */
    public function __construct(
        Enum\LogicalOperator $logicalOperator,
        public readonly string $field,
        public readonly Enum\Operator $operator,
        public readonly null|array|float|int|string|Enum\Type $value
    ) {
        parent::__construct($logicalOperator);
    }

    /**
     * @inheritDoc
     */
    public function evaluate(array $item, bool $nestingValues): bool
    {
        $value = $nestingValues
            ? $this->accessNestedValue($item, $this->field)
            : $item[$this->field]
                ?? $this->value;

        $compareValue = $this->value;
        if (is_scalar($this->value)) {
            $compareValue = $nestingValues
                ? ($this->accessNestedValue($item, (string) $this->value, false) ?? $this->value)
                : ($item[$this->value] ?? $this->value);
        }

        return $this->operator->evaluate(
            $value,
            $compareValue
        );
    }

    public function render(): string
    {
        return $this->operator->render($this->field, $this->value);
    }
}
