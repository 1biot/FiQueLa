<?php

namespace Conditions;

use FQL\Conditions\BaseConditionGroup;
use FQL\Conditions\SimpleCondition;
use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use FQL\Exception\UnexpectedValueException;
use PHPUnit\Framework\TestCase;

class BaseConditionGroupTest extends TestCase
{
    public function testInvalidContextThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid context invalid');

        new BaseConditionGroup('invalid');
    }

    public function testWhereGroupRender(): void
    {
        $group = new BaseConditionGroup('where');
        $group->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'age', Operator::GREATER_THAN, 18)
        );
        $group->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'status', Operator::EQUAL, 'active')
        );

        $this->assertTrue($group->isWhereGroup());
        $this->assertFalse($group->isHavingGroup());
        $this->assertSame("WHERE\n\tage > 18 AND status = 'active'", $group->render());
    }

    public function testHavingGroupRender(): void
    {
        $group = new BaseConditionGroup('having');
        $group->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'total', Operator::GREATER_THAN, 2)
        );

        $this->assertTrue($group->isHavingGroup());
        $this->assertFalse($group->isWhereGroup());
        $this->assertSame("HAVING\n\ttotal > 2", $group->render());
    }
}
