<?php

namespace UQL\Traits;

use UQL\Enum\LogicalOperator;
use UQL\Enum\Operator;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Helpers\ArrayHelper;
use UQL\Query\Query;

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
    public function where(string $key, Operator $operator, null|array|float|int|string $value): Query
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
    public function and(string $key, Operator $operator, null|int|float|string|array $value): Query
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
    public function or(string $key, Operator $operator, null|int|float|string|array $value): Query
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
    private function getConditionsArray(): array
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
        null|int|float|string|array $value
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
     * @param BaseCondition|Condition $condition
     * @return bool
     */
    private function evaluateConditionWithIteration(array $item, array $condition): bool
    {
        $key = $condition['key'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Recognizing iterations using []->key
        if (preg_match('/(.+)\[\]->(.+)/', $key, $matches)) {
            $arrayKey = $matches[1]; // For example "categories"
            $subKey = $matches[2];   // For example "id"

            // Retrieving array values
            $nestedValues = ArrayHelper::getNestedValue($item, $arrayKey, true);

            if (!is_array($nestedValues)) {
                throw new InvalidArgumentException(sprintf('Field "%s" is not iterable or does not exist', $arrayKey));
            }

            $values = array_map(fn($nestedItem) => $nestedItem[$subKey] ?? null, $nestedValues);

            // Evaluating the condition for iterations
            foreach ($values as $nestedValue) {
                if ($this->evaluateCondition($nestedValue, $operator, $value)) {
                    return true; // Condition satisfied for at least one value
                }
            }
            return false; // Condition not satisfied for any value
        }

        // Standard access to the value
        $fieldValue = ArrayHelper::getNestedValue($item, $key, true);

        return $this->evaluateCondition($fieldValue, $operator, $value);
    }


    /**
     * @param array<string|int, mixed> $item
     * @param BaseCondition[] $group
     */
    private function evaluateGroup(array $item, array $group): bool
    {
        foreach ($group as $condition) {
            if (!$this->evaluateConditionWithIteration($item, $condition)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param ConditionValue $operand
     */
    private function evaluateCondition(mixed $value, Operator $operator, null|int|float|string|array $operand): bool
    {
        return $operator->evaluate($value, $operand);
    }

    private function conditionsToString(): string
    {
        $conditions = $this->getConditionsArray();
        if (empty($conditions)) {
            return '';
        }

        return sprintf("\n%s %s", Query::WHERE, $this->convertConditions($conditions));
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
