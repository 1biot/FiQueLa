<?php

namespace Enum;

use PHPUnit\Framework\TestCase;
use FQL\Enum\Operator;

class OperatorTest extends TestCase
{
    public function testEvaluateEqual(): void
    {
        $this->assertSame(true, Operator::EQUAL->evaluate(1, "1"));
        $this->assertSame(true, Operator::EQUAL->evaluate(1, 1));
        $this->assertSame(true, Operator::EQUAL->evaluate("string1", "string1"));
        $this->assertSame(true, Operator::EQUAL->evaluate(0, null));
        $this->assertSame(false, Operator::EQUAL->evaluate("string1", "string2"));
    }

    public function testEvaluateNotEqual(): void
    {
        $this->assertSame(false, Operator::NOT_EQUAL->evaluate(1, "1"));
        $this->assertSame(false, Operator::NOT_EQUAL->evaluate(1, 1));
        $this->assertSame(false, Operator::NOT_EQUAL->evaluate("string1", "string1"));
        $this->assertSame(false, Operator::NOT_EQUAL->evaluate(0, null));
        $this->assertSame(true, Operator::NOT_EQUAL->evaluate("string1", "string2"));
    }

    public function testEvaluateEqualStrict(): void
    {
        $this->assertSame(true, Operator::EQUAL_STRICT->evaluate(1, 1));
        $this->assertSame(true, Operator::EQUAL_STRICT->evaluate("string1", "string1"));
        $this->assertSame(false, Operator::EQUAL_STRICT->evaluate(1, "1"));
        $this->assertSame(false, Operator::EQUAL_STRICT->evaluate(0, null));
        $this->assertSame(false, Operator::EQUAL_STRICT->evaluate("string1", "string2"));
    }

    public function testEvaluateNotEqualStrict(): void
    {
        $this->assertSame(false, Operator::NOT_EQUAL_STRICT->evaluate(1, 1));
        $this->assertSame(false, Operator::NOT_EQUAL_STRICT->evaluate("string1", "string1"));
        $this->assertSame(true, Operator::NOT_EQUAL_STRICT->evaluate(1, "1"));
        $this->assertSame(true, Operator::NOT_EQUAL_STRICT->evaluate(0, null));
        $this->assertSame(true, Operator::NOT_EQUAL_STRICT->evaluate("string1", "string2"));
    }

    public function testEvaluateGreaterThan(): void
    {
        $this->assertSame(true, Operator::GREATER_THAN->evaluate(2, 1));
        $this->assertSame(false, Operator::GREATER_THAN->evaluate(1, 1));
        $this->assertSame(false, Operator::GREATER_THAN->evaluate(0, 1));
    }

    public function testEvaluateGreaterThanEqual(): void
    {
        $this->assertSame(true, Operator::GREATER_THAN_OR_EQUAL->evaluate(2, 1));
        $this->assertSame(true, Operator::GREATER_THAN_OR_EQUAL->evaluate(1, 1));
        $this->assertSame(false, Operator::GREATER_THAN_OR_EQUAL->evaluate(0, 1));
    }

    public function testEvaluateLessThan(): void
    {
        $this->assertSame(false, Operator::LESS_THAN->evaluate(2, 1));
        $this->assertSame(false, Operator::LESS_THAN->evaluate(1, 1));
        $this->assertSame(true, Operator::LESS_THAN->evaluate(0, 1));
    }

    public function testEvaluateLessThanEqual(): void
    {
        $this->assertSame(false, Operator::LESS_THAN_OR_EQUAL->evaluate(2, 1));
        $this->assertSame(true, Operator::LESS_THAN_OR_EQUAL->evaluate(1, 1));
        $this->assertSame(true, Operator::LESS_THAN_OR_EQUAL->evaluate(0, 1));
    }

    public function testEvaluateIn(): void
    {
        $this->assertSame(true, Operator::IN->evaluate(1, [1, 2, 3]));
        $this->assertSame(false, Operator::IN->evaluate(4, [1, 2, 3]));
    }

    public function testEvaluateNotIn(): void
    {
        $this->assertSame(false, Operator::NOT_IN->evaluate(1, [1, 2, 3]));
        $this->assertSame(true, Operator::NOT_IN->evaluate(4, [1, 2, 3]));
    }

    public function testEvaluateContains(): void
    {
        $this->assertSame(true, Operator::CONTAINS->evaluate("string1", "string"));
        $this->assertSame(true, Operator::CONTAINS->evaluate("string1", "1"));
        $this->assertSame(false, Operator::CONTAINS->evaluate("string1", "number"));
    }

    public function testEvaluateStartsWith(): void
    {
        $this->assertSame(true, Operator::STARTS_WITH->evaluate("string1", "str"));
        $this->assertSame(false, Operator::STARTS_WITH->evaluate("string1", "1"));
        $this->assertSame(false, Operator::STARTS_WITH->evaluate("string1", "number"));
        $this->assertSame(true, Operator::STARTS_WITH->evaluate("1string", 1));
        $this->assertSame(true, Operator::STARTS_WITH->evaluate("1string", "1"));
    }

    public function testEvaluateEndsWith(): void
    {
        $this->assertSame(false, Operator::ENDS_WITH->evaluate("string1", "str"));
        $this->assertSame(true, Operator::ENDS_WITH->evaluate("string1", "1"));
        $this->assertSame(false, Operator::ENDS_WITH->evaluate("string1", "number"));
        $this->assertSame(true, Operator::ENDS_WITH->evaluate("string3", 3));
        $this->assertSame(true, Operator::ENDS_WITH->evaluate("string3", "3"));
    }
}
