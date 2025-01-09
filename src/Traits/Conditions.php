<?php

namespace UQL\Traits;

use UQL\Enum;
use UQL\Exceptions;
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

    /** @var null|ConditionGroup $currentGroup */
    private ?array $currentGroup = null;

    /**
     * @var Enum\LogicalOperator|null Actual logical operator for actual group context
     */
    private ?Enum\LogicalOperator $currentOperator = null;

    /** @var bool $havingGroupExists */
    private bool $havingGroupExists = false;

    /**
     * Switch context to WHERE and optionally add condition
     */
    public function where(?string $key = null, ?Enum\Operator $operator = null, mixed $value = null): Query
    {
        $this->currentContext = 'where';
        if ($key !== null && $operator !== null) {
            $this->addCondition(Enum\LogicalOperator::AND, $key, $operator, $value);
        }
        return $this;
    }

    /**
     * Switch context to HAVING and optionally add condition
     */
    public function having(?string $key = null, ?Enum\Operator $operator = null, mixed $value = null): Query
    {
        $this->currentContext = 'having';

        if (!$this->havingGroupExists) {
            $this->group(); // Automatically create HAVING group if group does not exist
            $this->havingGroupExists = true;
        }

        if ($key !== null && $operator !== null) {
            $this->addCondition(Enum\LogicalOperator::AND, $key, $operator, $value);
        }

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

    public function is(string $key, null|int|float|string $value): Query
    {
        return $this->and($key, Enum\Operator::EQUAL, $value);
    }

    public function orIs(string $key, null|int|float|string $value): Query
    {
        return $this->or($key, Enum\Operator::EQUAL, $value);
    }

    public function isNull(string $key): Query
    {
        return $this->and($key, Enum\Operator::EQUAL_STRICT, null);
    }

    public function orIsNull(string $key): Query
    {
        return $this->or($key, Enum\Operator::EQUAL_STRICT, null);
    }

    public function isNotNull(string $key): Query
    {
        return $this->and($key, Enum\Operator::NOT_EQUAL_STRICT, null);
    }

    public function orIsNotNull(string $key): Query
    {
        return $this->or($key, Enum\Operator::NOT_EQUAL_STRICT, null);
    }

    /**
     * @param InArrayList $values
     */
    public function in(string $key, array $values): Query
    {
        return $this->and($key, Enum\Operator::IN, $values);
    }

    /**
     * @param InArrayList $values
     */
    public function orIn(string $key, array $values): Query
    {
        return $this->or($key, Enum\Operator::IN, $values);
    }

    /**
     * @param InArrayList $values
     */
    public function notIn(string $key, array $values): Query
    {
        return $this->and($key, Enum\Operator::NOT_IN, $values);
    }

    /**
     * @param InArrayList $values
     */
    public function orNotIn(string $key, array $values): Query
    {
        return $this->or($key, Enum\Operator::NOT_IN, $values);
    }

    public function whereGroup(): Query
    {
        return $this->andGroup();
    }

    public function orGroup(): Query
    {
        $this->currentOperator = Enum\LogicalOperator::OR;
        return $this->group();
    }

    public function andGroup(): Query
    {
        $this->currentOperator = Enum\LogicalOperator::AND;
        return $this->group();
    }

    /**
     * Begins a new group of conditions in the current context.
     */
    public function group(): Query
    {
        if ($this->currentContext === 'having' && $this->havingGroupExists) {
            throw new \LogicException('Only one group is allowed in HAVING context.');
        }

        $group = [
            'type' => $this->currentOperator ?? Enum\LogicalOperator::AND,
            'group' => [],
        ];

        if ($this->currentGroup === null) {
            $this->currentGroup = &$group;
            $this->contexts[$this->currentContext][] = &$group;
        } else {
            $this->currentGroup['group'][] = &$group;
            $this->currentGroup = &$group;
        }

        $this->currentOperator = Enum\LogicalOperator::AND;
        return $this;
    }

    /**
     * Ends the current group of conditions in the current context.
     */
    public function endGroup(): Query
    {
        if ($this->currentContext === 'having' && !$this->havingGroupExists) {
            throw new \LogicException('No active group to end in HAVING context.');
        }

        if ($this->currentGroup === null) {
            throw new Exceptions\InvalidArgumentException('No active group to end.');
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
     */
    private function addCondition(Enum\LogicalOperator $type, string $key, Enum\Operator $operator, mixed $value): void
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

    private function conditionsToString(string $context): string
    {
        if (!isset($this->contexts[$context])) {
            throw new Exceptions\InvalidArgumentException("Unknown context: $context");
        }

        if (empty($this->contexts[$context])) {
            return '';
        }

        return rtrim(
            sprintf(
                PHP_EOL . "%s%s",
                $context === "where" ? Query::WHERE : Query::HAVING,
                $this->convertConditions($this->contexts[$context])
            )
        );
    }

    /**
     * Converts conditions to an SQL-like string.
     * @param ConditionArray $conditions
     */
    private function convertConditions(array $conditions, ?int $depth = null): string
    {
        $depth = $depth ?? 1;
        $parts = [];
        foreach ($conditions as $index => $condition) {
            if (isset($condition['group'])) {
                $groupConditionTypeString = (
                $index > 0
                    ? PHP_EOL . str_repeat("\t", $depth) . "{$condition['type']->value}" . ' '
                    : ''
                );
                $groupConditionTypeString .= '(';
                $groupConditionTypeString .= $this->convertConditions($condition['group'], $depth + 1);
                $groupConditionTypeString .= PHP_EOL . str_repeat("\t", $depth) . ')' . PHP_EOL;

                $parts[] = $groupConditionTypeString;
            } else {
                $key = $condition['key'];
                $operator = $condition['operator']->value;
                $value = is_string($condition['value']) ? "'{$condition['value']}'" : $condition['value'];
                $simpleConditionString = PHP_EOL . str_repeat("\t", $depth);
                $simpleConditionString .= ($index > 0 ? $condition['type']->value . ' ' : '');
                $simpleConditionString .= "$key $operator $value";
                $parts[] = $simpleConditionString;
            }
        }

        return implode(' ', $parts);
    }
}
