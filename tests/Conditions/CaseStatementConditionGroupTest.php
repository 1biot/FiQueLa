<?php

namespace Conditions;

use FQL\Conditions\CaseStatementConditionGroup;
use FQL\Conditions\SimpleCondition;
use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use PHPUnit\Framework\TestCase;

class CaseStatementConditionGroupTest extends TestCase
{
    public function testRenderUsesOnlyInnerConditions(): void
    {
        $group = new CaseStatementConditionGroup();
        $group->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'total', Operator::GREATER_THAN, 2)
        );

        $this->assertSame('total > 2', $group->render());
    }
}
