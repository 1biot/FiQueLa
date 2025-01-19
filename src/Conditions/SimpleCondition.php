<?php

namespace FQL\Conditions;

use FQL\Enum;
use FQL\Exceptions\UnexpectedValueException;
use FQL\Traits\Helpers;

class SimpleCondition extends Condition
{
    use Helpers\NestedArrayAccessor;

    public function __construct(
        Enum\LogicalOperator $logicalOperator,
        public readonly string $field,
        public readonly Enum\Operator $operator,
        public readonly mixed $value
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
                ?? throw new UnexpectedValueException(sprintf('Field %s not found in item', $this->field));

        return $this->operator->evaluate(
            $value,
            $this->value
        );
    }

    public function render(): string
    {
        return $this->operator->render($this->field, $this->value);
    }
}
