<?php

namespace FQL\Traits;

use FQL\Conditions\BaseConditionGroup;
use FQL\Conditions\Condition;
use FQL\Conditions\GroupCondition;
use FQL\Conditions\SimpleCondition;
use FQL\Enum;
use FQL\Exception;
use FQL\Interface;

/**
 * @phpstan-import-type ConditionValue from Interface\Query
 */
trait Conditions
{
    private BaseConditionGroup $whereConditions;
    private BaseConditionGroup $havingConditions;
    private GroupCondition $currentGroup;

    private function initialize(): Interface\Query
    {
        // Default groups for WHERE and HAVING
        $this->whereConditions = new BaseConditionGroup(Condition::WHERE);
        $this->havingConditions = new BaseConditionGroup(Condition::HAVING);

        // Setting the default current group to WHERE
        $this->currentGroup = $this->whereConditions;
        return $this;
    }

    /**
     * Switch context to WHERE and optionally add condition
     * @param ConditionValue $value
     */
    public function where(
        string $key,
        Enum\Operator $operator,
        array|float|int|string|Enum\Type $value
    ): Interface\Query {
        $this->addCondition(Enum\LogicalOperator::AND, $key, $operator, $value);
        return $this;
    }

    /**
     * Switch context to HAVING and optionally add condition
     * @param ConditionValue $value
     */
    public function having(
        string $key,
        Enum\Operator $operator,
        array|float|int|string|Enum\Type $value
    ): Interface\Query {
        $this->currentGroup = $this->havingConditions;
        $this->addCondition(Enum\LogicalOperator::AND, $key, $operator, $value);
        return $this;
    }

    /**
     * Add AND condition to current context
     * @param ConditionValue $value
     */
    public function and(
        string $key,
        Enum\Operator $operator,
        array|float|int|string|Enum\Type $value
    ): Interface\Query {
        $this->addCondition(Enum\LogicalOperator::AND, $key, $operator, $value);
        return $this;
    }

    /**
     * Add OR condition to current context
     * @param ConditionValue $value
     */
    public function or(
        string $key,
        Enum\Operator $operator,
        array|float|int|string|Enum\Type $value
    ): Interface\Query {
        $this->addCondition(Enum\LogicalOperator::OR, $key, $operator, $value);
        return $this;
    }

    /**
     * Add XOR condition to current context
     * @param ConditionValue $value
     */
    public function xor(
        string $key,
        Enum\Operator $operator,
        array|float|int|string|Enum\Type $value
    ): Interface\Query {
        $this->addCondition(Enum\LogicalOperator::XOR, $key, $operator, $value);
        return $this;
    }

    public function whereGroup(): Interface\Query
    {
        return $this->andGroup();
    }

    public function havingGroup(): Interface\Query
    {
        $this->currentGroup = $this->havingConditions;
        return $this->andGroup();
    }

    public function orGroup(): Interface\Query
    {
        return $this->beginGroup(Enum\LogicalOperator::OR);
    }

    public function andGroup(): Interface\Query
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
    public function endGroup(): Interface\Query
    {
        if ($this->currentGroup instanceof BaseConditionGroup) {
            throw new Exception\UnexpectedValueException('No group to end');
        }

        $this->currentGroup = $this->currentGroup->getParent() ?? $this->whereConditions;
        return $this;
    }

    /**
     * Add condition to the actual group context
     * @param ConditionValue $value
     */
    private function addCondition(
        Enum\LogicalOperator $type,
        string $key,
        Enum\Operator $operator,
        array|float|int|string|Enum\Type $value
    ): void {
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
