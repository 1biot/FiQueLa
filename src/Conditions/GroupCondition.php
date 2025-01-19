<?php

namespace FQL\Conditions;

use FQL\Enum;
use FQL\Enum\LogicalOperator;

class GroupCondition extends Condition implements \Countable
{
    /**
     * @var ConditionEnvelope[] $conditions
     */
    private array $conditions = [];
    protected int $depth = 0;

    public function __construct(
        Enum\LogicalOperator $logicalOperator,
        private readonly ?GroupCondition $parent = null
    ) {
        parent::__construct($logicalOperator);
        if ($this->parent !== null) {
            $this->depth = $this->parent->getDepth() + 1;
        }
    }

    /**
     * @param LogicalOperator $logicalOperator
     * @param Condition $condition
     * @return void
     */
    public function addCondition(Enum\LogicalOperator $logicalOperator, Condition $condition): void
    {
        $this->conditions[] = new ConditionEnvelope($logicalOperator, $condition);
    }

    /**
     * @return array<ConditionEnvelope>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function evaluate(array $item, bool $nestingValues): bool
    {
        $result = null;
        foreach ($this->conditions as $entry) {
            $conditionResult = $entry->condition->evaluate($item, $nestingValues);
            $result = $entry->logicalOperator->evaluate($result, $conditionResult);
        }
        return $result ?? true;
    }

    public function getParent(): ?GroupCondition
    {
        return $this->parent;
    }

    public function render(): string
    {
        $return = '(';
        foreach ($this->getConditions() as $index => $entry) {
            $index > 0
                && $return .= $entry->logicalOperator->render(true);
            $return .= $entry->condition->render();
        }

        return $return . ')';
    }

    public function count(): int
    {
        return count($this->conditions);
    }

    public function getDepth(): int
    {
        return $this->depth;
    }
}
