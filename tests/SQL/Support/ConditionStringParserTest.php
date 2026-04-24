<?php

namespace SQL\Support;

use FQL\Conditions\CaseStatementConditionGroup;
use FQL\Conditions\IfStatementConditionGroup;
use FQL\Conditions\SimpleCondition;
use FQL\Conditions\WhereConditionGroup;
use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use FQL\Enum\Type;
use FQL\Sql\Support\ConditionStringParser;
use PHPUnit\Framework\TestCase;

class ConditionStringParserTest extends TestCase
{
    public function testPopulatesSingleCondition(): void
    {
        $group = ConditionStringParser::populate('a = 1', new WhereConditionGroup());
        $entries = $group->getConditions();
        $this->assertCount(1, $entries);

        $condition = $entries[0]->condition;
        $this->assertInstanceOf(SimpleCondition::class, $condition);
        $this->assertSame('a', $condition->field);
        $this->assertSame(Operator::EQUAL, $condition->operator);
        $this->assertSame(1, $condition->value);
    }

    public function testPopulatesChainedAndConditions(): void
    {
        $group = ConditionStringParser::populate(
            'a = 1 AND b > 2 AND c IS NULL',
            new IfStatementConditionGroup()
        );
        $entries = $group->getConditions();
        $this->assertCount(3, $entries);
        $this->assertSame(LogicalOperator::AND, $entries[1]->logicalOperator);
    }

    public function testPopulatesOrXorConditions(): void
    {
        $group = ConditionStringParser::populate(
            'a = 1 OR b = 2 XOR c = 3',
            new CaseStatementConditionGroup()
        );
        $entries = $group->getConditions();
        $this->assertCount(3, $entries);
        $this->assertSame(LogicalOperator::OR, $entries[1]->logicalOperator);
        $this->assertSame(LogicalOperator::XOR, $entries[2]->logicalOperator);
    }

    public function testPopulatesNestedGroup(): void
    {
        $group = ConditionStringParser::populate(
            '(a = 1 OR b = 2) AND c = 3',
            new WhereConditionGroup()
        );
        $entries = $group->getConditions();
        $this->assertCount(2, $entries);
        // First entry is a nested GroupCondition
        $this->assertInstanceOf(\FQL\Conditions\GroupCondition::class, $entries[0]->condition);
        // Second entry is a SimpleCondition
        $this->assertInstanceOf(SimpleCondition::class, $entries[1]->condition);
    }

    public function testPopulatesInOperator(): void
    {
        $group = ConditionStringParser::populate(
            'id IN (1, 2, 3)',
            new WhereConditionGroup()
        );
        $condition = $group->getConditions()[0]->condition;
        $this->assertInstanceOf(SimpleCondition::class, $condition);
        $this->assertSame(Operator::IN, $condition->operator);
        $this->assertSame([1, 2, 3], $condition->value);
    }

    public function testPopulatesBetween(): void
    {
        $group = ConditionStringParser::populate(
            'n BETWEEN 1 AND 10',
            new WhereConditionGroup()
        );
        $condition = $group->getConditions()[0]->condition;
        $this->assertInstanceOf(SimpleCondition::class, $condition);
        $this->assertSame(Operator::BETWEEN, $condition->operator);
        $this->assertSame([1, 10], $condition->value);
    }

    public function testPopulatesIsNull(): void
    {
        $group = ConditionStringParser::populate(
            'a IS NULL',
            new WhereConditionGroup()
        );
        $condition = $group->getConditions()[0]->condition;
        $this->assertInstanceOf(SimpleCondition::class, $condition);
        $this->assertSame(Operator::IS, $condition->operator);
        $this->assertSame(Type::NULL, $condition->value);
    }

    public function testPopulatesStringComparison(): void
    {
        $group = ConditionStringParser::populate(
            'name = "Alice"',
            new WhereConditionGroup()
        );
        $condition = $group->getConditions()[0]->condition;
        $this->assertInstanceOf(SimpleCondition::class, $condition);
        $this->assertSame('Alice', $condition->value);
    }

    public function testPopulateReturnsSameInstanceForChaining(): void
    {
        $target = new WhereConditionGroup();
        $result = ConditionStringParser::populate('a = 1', $target);
        $this->assertSame($target, $result);
    }
}
