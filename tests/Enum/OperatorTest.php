<?php

namespace Enum;

use JQL\Enum\Operator;
use PHPUnit\Framework\TestCase;

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

    public function testEvaluateLike(): void
    {
        $this->assertSame(true, Operator::LIKE->evaluate("string1", "string"));
        $this->assertSame(false, Operator::LIKE->evaluate("string1", "number"));
    }
}
