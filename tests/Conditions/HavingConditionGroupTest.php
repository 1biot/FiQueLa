<?php

namespace Conditions;

use FQL\Conditions\HavingConditionGroup;
use FQL\Conditions\SimpleCondition;
use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use PHPUnit\Framework\TestCase;

class HavingConditionGroupTest extends TestCase
{
    public function testHavingGroupRender(): void
    {
        $group = new HavingConditionGroup();
        $group->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'total', Operator::GREATER_THAN, 2)
        );

        $this->assertSame("HAVING\n\ttotal > 2", $group->render());
    }
}
