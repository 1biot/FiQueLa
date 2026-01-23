<?php

namespace Enum;

use FQL\Enum\LogicalOperator;
use PHPUnit\Framework\TestCase;

class LogicalOperatorTest extends TestCase
{
    public function testEvaluateWithNullLeft(): void
    {
        $this->assertTrue(LogicalOperator::AND->evaluate(null, true));
        $this->assertFalse(LogicalOperator::AND->evaluate(null, false));
        $this->assertTrue(LogicalOperator::OR->evaluate(null, true));
        $this->assertFalse(LogicalOperator::OR->evaluate(null, false));
        $this->assertFalse(LogicalOperator::XOR->evaluate(null, true));
        $this->assertFalse(LogicalOperator::XOR->evaluate(null, false));
    }

    public function testEvaluateWithLeft(): void
    {
        $this->assertTrue(LogicalOperator::AND->evaluate(true, true));
        $this->assertFalse(LogicalOperator::AND->evaluate(true, false));
        $this->assertTrue(LogicalOperator::OR->evaluate(true, false));
        $this->assertFalse(LogicalOperator::OR->evaluate(false, false));
        $this->assertTrue(LogicalOperator::XOR->evaluate(true, false));
        $this->assertFalse(LogicalOperator::XOR->evaluate(true, true));
    }

    public function testRender(): void
    {
        $this->assertSame('AND', LogicalOperator::AND->render());
        $this->assertSame(' OR ', LogicalOperator::OR->render(true));
    }

    public function testCasesValues(): void
    {
        $this->assertSame(['AND', 'OR', 'XOR'], LogicalOperator::casesValues());
    }
}
