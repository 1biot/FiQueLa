<?php

namespace FQL\Traits;

use FQL\Conditions\BaseConditionGroup;
use FQL\Conditions\Condition;
use FQL\Conditions\GroupCondition;
use FQL\Conditions\SimpleCondition;
use FQL\Enum;
use FQL\Exception;
use FQL\Interface\Query;

trait Conditions
{
    private BaseConditionGroup $whereConditions;
    private BaseConditionGroup $havingConditions;
    private GroupCondition $currentGroup;

    private function initialize(): Query
    {
        // Výchozí skupiny pro WHERE a HAVING
        $this->whereConditions = new BaseConditionGroup(Condition::WHERE);
        $this->havingConditions = new BaseConditionGroup(Condition::HAVING);

        // Nastavení výchozí aktuální skupiny na WHERE
        $this->currentGroup = $this->whereConditions;
        return $this;
    }

    /**
     * Switch context to WHERE and optionally add condition
     */
    public function where(string $key, Enum\Operator $operator, null|array|float|int|string $value): Query
    {
        $this->addCondition(Enum\LogicalOperator::AND, $key, $operator, $value);
        return $this;
    }

    /**
     * Switch context to HAVING and optionally add condition
     */
    public function having(string $key, Enum\Operator $operator, null|array|float|int|string $value): Query
    {
        $this->currentGroup = $this->havingConditions;
        $this->addCondition(Enum\LogicalOperator::AND, $key, $operator, $value);
        return $this;
    }

    /**
     * Add AND condition to current context
     */
    public function and(string $key, Enum\Operator $operator, mixed $value): Query
    {
        $this->addCondition(Enum\LogicalOperator::AND, $key, $operator, $value);
        return $this;
    }

    /**
     * Add OR condition to current context
     */
    public function or(string $key, Enum\Operator $operator, mixed $value): Query
    {
        $this->addCondition(Enum\LogicalOperator::OR, $key, $operator, $value);
        return $this;
    }

    /**
     * Add XOR condition to current context
     */
    public function xor(string $key, Enum\Operator $operator, mixed $value): Query
    {
        $this->addCondition(Enum\LogicalOperator::XOR, $key, $operator, $value);
        return $this;
    }

    public function whereGroup(): Query
    {
        return $this->andGroup();
    }

    public function havingGroup(): Query
    {
        $this->currentGroup = $this->havingConditions;
        return $this->andGroup();
    }

    public function orGroup(): Query
    {
        return $this->beginGroup(Enum\LogicalOperator::OR);
    }

    public function andGroup(): Query
    {
        return $this->beginGroup(Enum\LogicalOperator::AND);
    }

    public function beginGroup(Enum\LogicalOperator $logicalOperator): self
    {
        // Create new group
        $group = new GroupCondition($logicalOperator, $this->currentGroup);
        $this->currentGroup->addCondition($logicalOperator, $group);

        // Set current group
        $this->currentGroup = $group;
        return $this;
    }

    /**
     * Ends the current group of conditions in the current context.
     */
    public function endGroup(): Query
    {
        if ($this->currentGroup instanceof BaseConditionGroup) {
            throw new Exception\UnexpectedValueException('No group to end');
        }

        $this->currentGroup = $this->currentGroup->getParent() ?? $this->whereConditions;
        return $this;
    }

    /**
     * Add condition to the actual group context
     */
    private function addCondition(Enum\LogicalOperator $type, string $key, Enum\Operator $operator, mixed $value): void
    {
        $this->currentGroup->addCondition($type, new SimpleCondition($type, $key, $operator, $value));
    }

    private function conditionsToString(BaseConditionGroup $context): string
    {
        if (empty($context->getConditions())) {
            return '';
        }

        return PHP_EOL . $context->render();
    }
}
