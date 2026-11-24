<?php

namespace FQL\Traits;

use FQL\Conditions\BaseConditionGroup;
use FQL\Conditions\Condition;
use FQL\Conditions\ExpressionCondition;
use FQL\Conditions\GroupCondition;
use FQL\Conditions\HavingConditionGroup;
use FQL\Conditions\SimpleCondition;
use FQL\Conditions\WhereConditionGroup;
use FQL\Enum;
use FQL\Exception;
use FQL\Interface;
use FQL\Sql;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Printer\FqlPrinter;
use FQL\Sql\Printer\UnprintableNodeException;
use FQL\Sql\Token\Position;

/**
 * @phpstan-import-type ConditionValue from Interface\Query
 */
trait Conditions
{
    private BaseConditionGroup $whereConditions;
    private BaseConditionGroup $havingConditions;
    private GroupCondition $currentGroup;
    private bool $conditionsBlocked = false;

    public function blockConditions(): void
    {
        $this->conditionsBlocked = true;
    }

    public function isConditionsEmpty(): bool
    {
        return count($this->whereConditions) === 0 && count($this->havingConditions) === 0;
    }

    private function initialize(): Interface\Query
    {
        // Default groups for WHERE and HAVING
        $this->whereConditions = new WhereConditionGroup();
        $this->havingConditions = new HavingConditionGroup();

        // Setting the default current group to WHERE
        $this->currentGroup = $this->whereConditions;
        return $this;
    }

    public function addWhereConditions(WhereConditionGroup $whereConditionGroup): Interface\Query
    {
        if (isset($this->whereConditions) && count($this->whereConditions) > 0) {
            throw new Exception\UnexpectedValueException(
                'Cannot override WHERE conditions after conditions have been added'
            );
        }

        $isCurrentWhereGroup = isset($this->currentGroup) && $this->currentGroup === $this->whereConditions;
        $this->whereConditions = $whereConditionGroup;
        if ($isCurrentWhereGroup) {
            $this->currentGroup = $this->whereConditions;
        }

        return $this;
    }

    public function addHavingConditions(HavingConditionGroup $havingConditionGroup): Interface\Query
    {
        if (isset($this->havingConditions) && count($this->havingConditions) > 0) {
            throw new Exception\UnexpectedValueException(
                'Cannot override HAVING conditions after conditions have been added'
            );
        }

        $isCurrentHavingGroup = isset($this->currentGroup) && $this->currentGroup === $this->havingConditions;
        $this->havingConditions = $havingConditionGroup;
        if ($isCurrentHavingGroup) {
            $this->currentGroup = $this->havingConditions;
        }

        return $this;
    }

    /**
     * Switch context to WHERE and add condition. Inside an open WHERE group the
     * condition joins that group; after having() it goes back to WHERE.
     * @param ConditionValue $value
     */
    public function where(
        string $key,
        Enum\Operator $operator,
        array|float|int|string|Enum\Type $value
    ): Interface\Query {
        $this->switchToWhereContext();
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
        $this->switchToWhereContext();
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
     * Moves the current context back to WHERE when it is in HAVING (the HAVING
     * root or a group nested in it). A context already inside WHERE, including
     * an open WHERE group, is kept.
     */
    private function switchToWhereContext(): void
    {
        if ($this->isHavingContext()) {
            $this->currentGroup = $this->whereConditions;
        }
    }

    private function isHavingContext(): bool
    {
        $root = $this->currentGroup;
        while (($parent = $root->getParent()) !== null) {
            $root = $parent;
        }

        return $root === $this->havingConditions;
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
        if ($this->conditionsBlocked) {
            throw new Exception\QueryLogicException('WHERE/HAVING is not allowed in DESCRIBE mode');
        }

        $this->currentGroup->addCondition($type, $this->createCondition($type, $key, $operator, $value));
    }

    /**
     * A plain field path (`price`, `brand.code`, `my-field`, `items.0.price`) becomes a
     * {@see SimpleCondition} as before; any other expression (function call,
     * arithmetic, CASE, CAST, …) an {@see ExpressionCondition} evaluated like the same
     * condition in FQL. A key the expression parser rejects stays a field path, and so
     * does a HAVING key naming a selected output column (`SUM(price)` selected without
     * an alias is a column named `SUM(price)`).
     *
     * @param ConditionValue $value
     */
    private function createCondition(
        Enum\LogicalOperator $type,
        string $key,
        Enum\Operator $operator,
        array|float|int|string|Enum\Type $value
    ): Condition {
        $expression = $this->conditionExpression($key);
        if ($expression === null) {
            return new SimpleCondition($type, $key, $operator, $value);
        }

        return new ExpressionCondition($type, $expression, $operator, $this->conditionRightSide($value));
    }

    private function conditionExpression(string $key): ?ExpressionNode
    {
        if ($this->isHavingContext() && isset($this->selectedFields[$key])) {
            return null;
        }

        try {
            $node = Sql\Provider::parseExpression($key);
        } catch (Sql\Parser\ParseException) {
            return null;
        }

        return $node instanceof ColumnReferenceNode || $node instanceof LiteralNode ? null : $node;
    }

    /**
     * The fluent value as the right side of an expression condition, read as FQL reads
     * the rendered condition: scalars are literals, a backticked string is a column and
     * a string in quotes (`'"active"'`) is the literal between them.
     *
     * @param ConditionValue $value
     * @return ExpressionNode|Enum\Type|ExpressionNode[]
     */
    private function conditionRightSide(array|float|int|string|Enum\Type $value): ExpressionNode|Enum\Type|array
    {
        if ($value instanceof Enum\Type) {
            return $value;
        }
        if (is_array($value)) {
            return array_values(array_map(
                fn (mixed $item): ExpressionNode => $this->conditionValueNode($item),
                $value
            ));
        }

        return $this->conditionValueNode($value);
    }

    private function conditionValueNode(mixed $value): ExpressionNode
    {
        if (is_string($value) && preg_match('/^`.+`$/s', $value) === 1) {
            return new ColumnReferenceNode(trim($value, '`'), Position::synthetic());
        }
        if (is_string($value) && preg_match('/^(["\'])(.*)\1$/s', $value, $matches) === 1) {
            $value = $matches[2];
        }

        $raw = match (true) {
            is_string($value) => $this->stringLiteralRaw($value),
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            $value === null => 'NULL',
            is_scalar($value) => (string) $value,
            default => '',
        };

        return new LiteralNode($value, Enum\Type::match($value), $raw, Position::synthetic());
    }

    private function stringLiteralRaw(string $value): string
    {
        try {
            return FqlPrinter::stringLiteral($value);
        } catch (UnprintableNodeException) {
            return '';
        }
    }

    private function conditionsToString(BaseConditionGroup $context): string
    {
        if (empty($context->getConditions())) {
            return '';
        }

        return PHP_EOL . $context->render();
    }
}
