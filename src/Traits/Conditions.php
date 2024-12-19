<?php

namespace JQL\Traits;

use JQL\Enum\LogicalOperator;
use JQL\Enum\Operator;
use JQL\Helpers\ArrayHelper;

/**
 * @phpstan-type ConditionValue int|float|string|int[]|string[]|array<int|string>
 * @phpstan-type BaseCondition array{
 *     key: string,
 *     operator: Operator,
 *     value: ConditionValue
 * }
 * @phpstan-type Condition array{
 *     type: LogicalOperator,
 *     key: string,
 *     operator: Operator,
 *     value: ConditionValue
 * }
 * @phpstan-type ConditionGroup array{
 *     type: LogicalOperator,
 *     group: BaseCondition[]
 * }
 */
trait Conditions
{
    private bool $useGrouping = true;

    private ?LogicalOperator $currentOperator = null;

    /** @var BaseCondition[] $currentGroup */
    private array $currentGroup = [];

    /** @var array<Condition|ConditionGroup> $conditions */
    private array $conditions = [];

    public function setGrouping(bool $grouping): self
    {
        $this->useGrouping = $grouping;
        return $this;
    }

    /**
     * @param ConditionValue $value
     */
    public function where(string $key, Operator $operator, int|float|string|array $value): self
    {
        if ($this->couldFlushGroup(LogicalOperator::AND)) {
            $this->flushGroup(); // Ukončí předchozí skupinu
        }

        $this->currentOperator = LogicalOperator::AND;
        $this->addCondition(LogicalOperator::AND, $key, $operator, $value);
        return $this;
    }

    /**
     * @param ConditionValue $value
     */
    public function and(string $key, Operator $operator, int|float|string|array $value): self
    {
        return $this->where($key, $operator, $value);
    }

    /**
     * @param ConditionValue $value
     */
    public function or(string $key, Operator $operator, int|float|string|array $value): self
    {
        if ($this->couldFlushGroup(LogicalOperator::OR)) {
            $this->flushGroup(); // Ukončí předchozí skupinu
        }

        $this->currentOperator = LogicalOperator::OR;
        $this->addCondition($this->useGrouping ? LogicalOperator::AND : LogicalOperator::OR, $key, $operator, $value);
        return $this;
    }

    /**
     * @return array<Condition|ConditionGroup>
     */

    public function getConditionsArray(): array
    {
        return array_merge(
            $this->conditions,
            empty($this->currentGroup) ? [] : [['type' => $this->currentOperator, 'group' => $this->currentGroup]]
        );
    }

    /**
     * @param ConditionValue $value
     */
    private function addCondition(
        LogicalOperator $type,
        string $key,
        Operator $operator,
        int|float|string|array $value
    ): void {
        if ($this->useGrouping) {
            $this->currentGroup[] = ['key' => $key, 'operator' => $operator, 'value' => $value];
        } else {
            $this->conditions[] = ['type' => $type, 'key' => $key, 'operator' => $operator, 'value' => $value];
        }
    }

    private function couldFlushGroup(LogicalOperator $operator): bool
    {
        return !$this->useGrouping
            || ($this->currentOperator !== null && $this->currentOperator !== $operator);
    }

    private function flushGroup(): void
    {
        if (!empty($this->currentGroup)) {
            $this->conditions[] = [
                'type' => $this->currentOperator,
                'group' => $this->currentGroup
            ];
            $this->currentGroup = [];
        }
    }

    /**
     * @param array<string|int, mixed> $item
     * @param BaseCondition[] $group
     */
    private function evaluateGroup(array $item, array $group): bool
    {
        foreach ($group as $condition) {
            $value = ArrayHelper::getNestedValue($item, $condition['key']);
            if (
                !$this->evaluateCondition(
                    $value,
                    $condition['operator'],
                    $condition['value']
                )
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     * @param Operator $operator
     * @param ConditionValue $operand
     * @return bool
     */
    private function evaluateCondition(mixed $value, Operator $operator, int|float|string|array $operand): bool
    {
        return $operator->evaluate($value, $operand);
    }

    /**
     * @param array<BaseCondition|Condition|ConditionGroup> $conditions
     * @return string
     */
    private function conditionsToString(array $conditions): string
    {
        $queryParts = [];
        foreach ($conditions as $index => $condition) {
            if (isset($condition['group'])) {
                // Reprezentace skupiny
                $groupString = '(' . $this->conditionsToString($condition['group']) . "\n)";
                $queryParts[] = ($index > 0 ? $condition['type']->value . ' ' : '') . $groupString;
            } else {
                // Reprezentace jednoduché podmínky
                $key = $condition['key'];
                $operator = $condition['operator']->value;
                $value = is_string($condition['value']) ? "'{$condition['value']}'" : $condition['value'];
                $conditionPart = "\n\t";
                if ($index > 0) {
                    if (isset($condition['type'])) {
                        $conditionPart .= $condition['type']->value;
                    } else {
                        $conditionPart .= 'AND';
                    }
                }
                $conditionPart .= " $key $operator $value";
                $queryParts[] = $conditionPart;
            }
        }

        return implode(' ', $queryParts);
    }
}
