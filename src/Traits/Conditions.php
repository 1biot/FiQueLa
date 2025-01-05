<?php

namespace UQL\Traits;

use UQL\Enum\LogicalOperator;
use UQL\Enum\Operator;
use UQL\Exceptions\InvalidArgumentException;
use LogicException;
use UQL\Helpers\ArrayHelper;
use UQL\Query\Query;
use UQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type InArrayList from Query
 * @phpstan-import-type ConditionValue from Query
 * @phpstan-import-type Condition from Query
 * @phpstan-import-type ConditionGroup from Query
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 * @phpstan-type ConditionArray array<Condition|ConditionGroup>
 * @phpstan-type ConditionsArray array{where: ConditionArray, having: ConditionArray}
 */
trait Conditions
{
    /**
     * @var ConditionsArray $contexts
     */
    private array $contexts = [
        'where' => [],
        'having' => [],
    ];

    /**
     * @var string $currentContext Actual group context ("where" or "having")
     */
    private string $currentContext = 'where';

    /** @var ConditionGroup|null $currentGroup */
    private ?array $currentGroup = null;

    /**
     * @var LogicalOperator|null Actual logical operator for actual group context
     */
    private ?LogicalOperator $currentOperator = null;

    /** @var bool $havingGroupExists */
    private bool $havingGroupExists = false;

    /**
     * Switch context to WHERE and optionally add condition
     * @param string|null $key Field name
     * @param Operator|null $operator Operator
     * @param mixed $value Comparison value
     */
    public function where(?string $key = null, ?Operator $operator = null, mixed $value = null): Query
    {
        $this->currentContext = 'where';
        if ($key !== null && $operator !== null) {
            $this->addCondition(LogicalOperator::AND, $key, $operator, $value);
        }
        return $this;
    }

    /**
     * Switch context to HAVING and optionally add condition
     * @param string|null $key Field name
     * @param Operator|null $operator Operator
     * @param mixed $value Comparison value
     */
    public function having(?string $key = null, ?Operator $operator = null, mixed $value = null): Query
    {
        $this->currentContext = 'having';

        if (!$this->havingGroupExists) {
            $this->group(); // Automatically create HAVING group if group does not exist
            $this->havingGroupExists = true;
        }

        if ($key !== null && $operator !== null) {
            $this->addCondition(LogicalOperator::AND, $key, $operator, $value);
        }

        return $this;
    }

    /**
     * Add AND condition to current context
     * @param string $key Field name
     * @param Operator $operator Operator
     * @param mixed $value Comparison value
     */
    public function and(string $key, Operator $operator, mixed $value): Query
    {
        $this->addCondition(LogicalOperator::AND, $key, $operator, $value);
        return $this;
    }

    /**
     * Add OR condition to current context
     * @param string $key Field name
     * @param Operator $operator Operator
     * @param mixed $value Comparison value
     */
    public function or(string $key, Operator $operator, mixed $value): Query
    {
        $this->addCondition(LogicalOperator::OR, $key, $operator, $value);
        return $this;
    }

    public function is(string $key, null|int|float|string $value): Query
    {
        return $this->and($key, Operator::EQUAL, $value);
    }

    public function orIs(string $key, null|int|float|string $value): Query
    {
        return $this->or($key, Operator::EQUAL, $value);
    }

    public function isNull(string $key): Query
    {
        return $this->and($key, Operator::EQUAL_STRICT, null);
    }

    public function orIsNull(string $key): Query
    {
        return $this->or($key, Operator::EQUAL_STRICT, null);
    }

    public function isNotNull(string $key): Query
    {
        return $this->and($key, Operator::NOT_EQUAL_STRICT, null);
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
        return $this->and($key, Operator::IN, $values);
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
        return $this->and($key, Operator::NOT_IN, $values);
    }

    /**
     * @param InArrayList $values
     */
    public function orNotIn(string $key, array $values): Query
    {
        return $this->or($key, Operator::NOT_IN, $values);
    }

    public function whereGroup(): Query
    {
        return $this->andGroup();
    }

    public function orGroup(): Query
    {
        $this->currentOperator = LogicalOperator::OR;
        return $this->group();
    }

    public function andGroup(): Query
    {
        $this->currentOperator = LogicalOperator::AND;
        return $this->group();
    }

    /**
     * Begins a new group of conditions in the current context.
     */
    public function group(): Query
    {
        if ($this->currentContext === 'having' && $this->havingGroupExists) {
            throw new LogicException('Only one group is allowed in HAVING context.');
        }

        $group = [
            'type' => $this->currentOperator ?? LogicalOperator::AND,
            'group' => [],
        ];

        if ($this->currentGroup === null) {
            $this->currentGroup = &$group;
            $this->contexts[$this->currentContext][] = &$group;
        } else {
            $this->currentGroup['group'][] = &$group;
            $this->currentGroup = &$group;
        }

        $this->currentOperator = LogicalOperator::AND;
        return $this;
    }

    /**
     * Ends the current group of conditions in the current context.
     */
    public function endGroup(): Query
    {
        if ($this->currentContext === 'having' && !$this->havingGroupExists) {
            throw new LogicException('No active group to end in HAVING context.');
        }

        if ($this->currentGroup === null) {
            throw new InvalidArgumentException('No active group to end.');
        }

        // we find a parent group, if exists
        $found = false;
        foreach ($this->contexts[$this->currentContext] as &$condition) {
            if (isset($condition['group']) && $condition['group'] === $this->currentGroup['group']) {
                $this->currentGroup = $condition;
                $found = true;
                break;
            }
        }

        if (!$found) {
            // it is top level group, we reset actual group
            $this->currentGroup = null;
        }

        if ($this->currentContext === 'having') {
            $this->havingGroupExists = false;
        }

        return $this;
    }


    /**
     * Add condition to the actual group context
     * @param LogicalOperator $type Typ of logical operator (AND/OR)
     * @param string $key Field name
     * @param Operator $operator Operator
     * @param mixed $value Comparison value
     */
    private function addCondition(LogicalOperator $type, string $key, Operator $operator, mixed $value): void
    {
        $condition = [
            'key' => $key,
            'operator' => $operator,
            'value' => $value,
            'type' => $type,
        ];

        if ($this->currentGroup !== null) {
            $this->currentGroup['group'][] = $condition;
        } else {
            $this->contexts[$this->currentContext][] = $condition;
        }
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     */
    private function evaluateWhereConditions(array $item): bool
    {
        return $this->evaluateConditions('where', $item, true);
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     * @param string[] $allowedFields
     */
    private function evaluateHavingConditions(array $item, array $allowedFields = []): bool
    {
        $proxyItem = [];
        foreach ($allowedFields as $allowedField) {
            if (!isset($item[$allowedField])) {
                continue;
            }
            $proxyItem[$allowedField] = $item[$allowedField];
        }
        return $this->evaluateConditions('having', $proxyItem, false);
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     */
    private function evaluateConditions(string $context, array $item, bool $nestingValues): bool
    {
        if (!isset($this->contexts[$context])) {
            throw new InvalidArgumentException("Unknown context: $context");
        }

        if (empty($this->contexts[$context])) {
            return true;
        }

        return $this->evaluateGroup($item, $this->contexts[$context], $nestingValues);
    }

    /**
     * Evaluate group of conditions
     * @param StreamProviderArrayIteratorValue $item
     * @param array<Condition|ConditionGroup> $conditions
     * @return bool
     */
    private function evaluateGroup(array $item, array $conditions, bool $nestingValues): bool
    {
        $result = null;
        foreach ($conditions as $condition) {
            if (isset($condition['group'])) {
                // Recursive evaluate of nested group
                $groupResult = $this->evaluateGroup($item, $condition['group'], $nestingValues);
            } else {
                // Evaluate of simple condition
                $groupResult = $this->evaluateCondition(
                    $nestingValues
                        ? ArrayHelper::getNestedValue($item, $condition['key'])
                        : $item[$condition['key']]
                            ?? throw new InvalidArgumentException(sprintf("Field '%s' not found.", $condition['key'])),
                    $condition['operator'],
                    $condition['value']
                );
            }

            if ($condition['type'] === LogicalOperator::AND) {
                $result = $result === null ? $groupResult : $result && $groupResult;
            } elseif ($condition['type'] === LogicalOperator::OR) {
                $result = $result === null ? $groupResult : $result || $groupResult;
            }
        }

        return $result ?? true; // When we have no more conditions, returns true
    }

    /**
     * Evaluate of simple condition
     * @param mixed $value Concrete value
     * @param Operator $operator Operator
     * @param mixed $operand Comparison value
     * @return bool
     */
    private function evaluateCondition(mixed $value, Operator $operator, mixed $operand): bool
    {
        return $operator->evaluate($value, $operand);
    }

    private function conditionsToString(string $context): string
    {
        if (!isset($this->contexts[$context])) {
            throw new InvalidArgumentException("Unknown context: $context");
        }

        if (empty($this->contexts[$context])) {
            return '';
        }

        return rtrim(
            sprintf(
                "\n%s%s",
                $context === "where" ? Query::WHERE : Query::HAVING,
                $this->convertConditions($this->contexts[$context])
            )
        );
    }

    /**
     * Converts conditions to an SQL-like string.
     * @param array<Condition|ConditionGroup> $conditions
     * @return string
     */
    private function convertConditions(array $conditions): string
    {
        $parts = [];
        foreach ($conditions as $index => $condition) {
            if (isset($condition['group'])) {
                $groupString = '(' . $this->convertConditions($condition['group']) . ')' . PHP_EOL;
                $parts[] = ($index > 0 ? $condition['type']->value . ' ' : '') . $groupString;
            } else {
                $key = $condition['key'];
                $operator = $condition['operator']->value;
                $value = is_string($condition['value']) ? "'{$condition['value']}'" : $condition['value'];
                $parts[] = "\n\t" . ($index > 0 ? $condition['type']->value . ' ' : '') . "{$key} {$operator} {$value}";
            }
        }

        return implode(' ', $parts);
    }
}
