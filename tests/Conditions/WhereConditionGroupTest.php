<?php

namespace Conditions;

use FQL\Conditions\SimpleCondition;
use FQL\Conditions\WhereConditionGroup;
use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use PHPUnit\Framework\TestCase;

class WhereConditionGroupTest extends TestCase
{
    public function testWhereGroupRender(): void
    {
        $group = new WhereConditionGroup();
        $group->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'age', Operator::GREATER_THAN, 18)
        );
        $group->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'status', Operator::EQUAL, 'active')
        );

        $this->assertSame("WHERE\n\tage > 18 AND status = 'active'", $group->render());
    }
}
