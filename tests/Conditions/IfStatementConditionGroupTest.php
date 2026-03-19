<?php

namespace Conditions;

use FQL\Conditions\IfStatementConditionGroup;
use FQL\Conditions\SimpleCondition;
use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use PHPUnit\Framework\TestCase;

class IfStatementConditionGroupTest extends TestCase
{
    public function testRenderUsesOnlyInnerConditions(): void
    {
        $group = new IfStatementConditionGroup();
        $group->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'age', Operator::GREATER_THAN, 18)
        );
        $group->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'status', Operator::EQUAL, 'active')
        );

        $this->assertSame("age > 18 AND status = 'active'", $group->render());
    }
}
