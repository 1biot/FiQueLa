<?php

namespace FQL\Conditions;

use FQL\Enum;
use FQL\Exception;

final class BaseConditionGroup extends GroupCondition
{
    private readonly string $context;

    public function __construct(string $context)
    {
        if (!in_array($context, [Condition::WHERE, Condition::HAVING])) {
            throw new Exception\UnexpectedValueException(sprintf('Invalid context %s', $context));
        }

        $this->context = $context;
        parent::__construct(Enum\LogicalOperator::AND);
    }

    public function isWhereGroup(): bool
    {
        return $this->context === Condition::WHERE;
    }

    public function isHavingGroup(): bool
    {
        return $this->context === Condition::HAVING;
    }

    public function render(): string
    {
        $return = match (true) {
            $this->isWhereGroup() => 'WHERE' . PHP_EOL . "\t",
            $this->isHavingGroup() => 'HAVING' . PHP_EOL . "\t",
            default => '',
        };

        foreach ($this->getConditions() as $index => $entry) {
            $index > 0
                && $return .= $entry->logicalOperator->render(true);
            $return .= $entry->condition->render();
        }

        return $return;
    }
}
