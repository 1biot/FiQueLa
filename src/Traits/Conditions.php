<?php

namespace JQL\Traits;

use JQL\Enum\LogicalOperator;
use JQL\Enum\Operator;
use JQL\Helpers\ArrayHelper;
use JQL\Query;

/**
 * @phpstan-import-type InArrayList from Query
 * @phpstan-import-type ConditionValue from Query
 * @phpstan-import-type BaseCondition from Query
 * @phpstan-import-type Condition from Query
 * @phpstan-import-type ConditionGroup from Query
 */
trait Conditions
{
    private bool $useGrouping = true;

    private ?LogicalOperator $currentOperator = null;

    /** @var BaseCondition[] $currentGroup */
    private array $currentGroup = [];

    /** @var array<Condition|ConditionGroup> $conditions */
    private array $conditions = [];

    public function setGrouping(bool $grouping): Query
    {
        $this->useGrouping = $grouping;
        return $this;
    }

    /**
     * @param ConditionValue $value
     */
    public function where(string $key, Operator $operator, array|float|int|string $value): Query
    {
        if ($this->couldFlushGroup(LogicalOperator::AND)) {
            $this->flushGroup(); // End previous group
        }

        $this->currentOperator = LogicalOperator::AND;
        $this->addCondition(LogicalOperator::AND, $key, $operator, $value);
        return $this;
    }

    /**
     * @param ConditionValue $value
     */
    public function and(string $key, Operator $operator, int|float|string|array $value): Query
    {
        return $this->where($key, $operator, $value);
    }

    public function is(string $key, null|int|float|string $value): Query
    {
        return $this->where($key, Operator::EQUAL, $value);
    }

    public function orIs(string $key, null|int|float|string $value): Query
    {
        return $this->or($key, Operator::EQUAL, $value);
    }

    public function isNull(string $key): Query
    {
        return $this->where($key, Operator::EQUAL_STRICT, null);
    }

    public function orIsNull(string $key): Query
    {
        return $this->or($key, Operator::EQUAL_STRICT, null);
    }

    public function isNotNull(string $key): Query
    {
        return $this->where($key, Operator::NOT_EQUAL_STRICT, null);
    }

    public function orIsNotNull(string $key): Query
    {
        return $this->or($key, Operator::NOT_EQUAL_STRICT, null);
    }

    /**
     * @param InArrayList $values
     */
    public function in(string $key, array $values): Query
    {
        return $this->where($key, Operator::IN, $values);
    }

    /**
     * @param InArrayList $values
     */
    public function orIn(string $key, array $values): Query
    {
        return $this->or($key, Operator::IN, $values);
    }

    /**
     * @param InArrayList $values
     */
    public function notIn(string $key, array $values): Query
    {
        return $this->where($key, Operator::NOT_IN, $values);
    }

    /**
     * @param InArrayList $values
     */
    public function orNotIn(string $key, array $values): Query
    {
        return $this->or($key, Operator::NOT_IN, $values);
    }

    /**
     * @param ConditionValue $value
     */
    public function or(string $key, Operator $operator, int|float|string|array $value): Query
    {
        if ($this->couldFlushGroup(LogicalOperator::OR)) {
            $this->flushGroup(); // End previous group
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
     * @param ConditionValue $operand
     */
    private function evaluateCondition(mixed $value, Operator $operator, int|float|string|array $operand): bool
    {
        return $operator->evaluate($value, $operand);
    }

    private function conditionsToString(): string
    {
        $conditions = $this->getConditionsArray();
        if (empty($conditions)) {
            return '';
        }

        return sprintf("\nWHERE %s", $this->convertConditions($conditions));
    }

    /**
     * @param array<BaseCondition|Condition|ConditionGroup> $conditions
     */
    private function convertConditions(array $conditions): string
    {
        $queryParts = [];
        foreach ($conditions as $index => $condition) {
            if (isset($condition['group'])) {
                // Representation of group
                $groupString = '(' . $this->convertConditions($condition['group']) . "\n)";
                $queryParts[] = ($index > 0 ? $condition['type']->value . ' ' : '') . $groupString;
            } else {
                // Representation of simple condition
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

        if (count($queryParts) === 0) {
            return '';
        }

        return implode(' ', $queryParts);
    }
}
