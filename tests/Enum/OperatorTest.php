<?php

namespace Enum;

use FQL\Enum\Type;
use FQL\Exception\InvalidArgumentException;
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
        $this->assertSame(true, Operator::LIKE->evaluate("string1", "%string%"));
        $this->assertSame(true, Operator::LIKE->evaluate("string1", "%1%"));
        $this->assertSame(false, Operator::LIKE->evaluate("string1", "%number%"));
    }

    public function testEvaluateStartsWith(): void
    {
        $this->assertSame(true, Operator::LIKE->evaluate("string1", "str%"));
        $this->assertSame(false, Operator::LIKE->evaluate("string1", "1%"));
        $this->assertSame(false, Operator::LIKE->evaluate("string1", "number%"));
        $this->assertSame(true, Operator::LIKE->evaluate("1string", "1%"));
    }

    public function testEvaluateEndsWith(): void
    {
        $this->assertSame(false, Operator::LIKE->evaluate("string1", "%str"));
        $this->assertSame(true, Operator::LIKE->evaluate("string1", "%1"));
        $this->assertSame(false, Operator::LIKE->evaluate("string1", "%number"));
        $this->assertSame(true, Operator::LIKE->evaluate("string3", "%3"));
    }

    public function testEvaluateNotContains(): void
    {
        $this->assertSame(false, Operator::NOT_LIKE->evaluate("string1", "%string%"));
        $this->assertSame(false, Operator::NOT_LIKE->evaluate("string1", "%1%"));
        $this->assertSame(true, Operator::NOT_LIKE->evaluate("string1", "%number%"));
    }

    public function testEvaluateNotStartsWith(): void
    {
        $this->assertSame(false, Operator::NOT_LIKE->evaluate("string1", "str%"));
        $this->assertSame(true, Operator::NOT_LIKE->evaluate("string1", "1%"));
        $this->assertSame(true, Operator::NOT_LIKE->evaluate("string1", "number%"));
        $this->assertSame(false, Operator::NOT_LIKE->evaluate("1string", "1%"));
    }

    public function testEvaluateNotEndsWith(): void
    {
        $this->assertSame(true, Operator::NOT_LIKE->evaluate("string1", "%str"));
        $this->assertSame(false, Operator::NOT_LIKE->evaluate("string1", "%1"));
        $this->assertSame(true, Operator::NOT_LIKE->evaluate("string1", "%number"));
        $this->assertSame(false, Operator::NOT_LIKE->evaluate("string3", "%3"));
    }

    public function testEvaluateIs(): void
    {
        $this->assertSame(true, Operator::IS->evaluate(null, Type::NULL));
        $this->assertSame(true, Operator::IS->evaluate(0, Type::INTEGER));
        $this->assertSame(true, Operator::IS->evaluate(0.0, Type::FLOAT));
        $this->assertSame(true, Operator::IS->evaluate(0, Type::NUMBER));
        $this->assertSame(true, Operator::IS->evaluate('0', Type::STRING));
        $this->assertSame(true, Operator::IS->evaluate('', Type::STRING));
        $this->assertSame(true, Operator::IS->evaluate('test', Type::STRING));
        $this->assertSame(true, Operator::IS->evaluate(true, Type::TRUE));
        $this->assertSame(true, Operator::IS->evaluate(false, Type::FALSE));
        $this->assertSame(true, Operator::IS->evaluate(true, Type::BOOLEAN));
        $this->assertSame(true, Operator::IS->evaluate(false, Type::BOOLEAN));
        $this->assertSame(true, Operator::IS->evaluate([], Type::ARRAY));
        $this->assertSame(true, Operator::IS->evaluate(new \stdClass(), Type::OBJECT));

        $this->assertSame(false, Operator::IS->evaluate(null, Type::NUMBER));
        $this->assertSame(false, Operator::IS->evaluate(0, Type::NULL));
        $this->assertSame(false, Operator::IS->evaluate(0.0, Type::NULL));
        $this->assertSame(false, Operator::IS->evaluate(0, Type::STRING));
        $this->assertSame(false, Operator::IS->evaluate(true, Type::STRING));
        $this->assertSame(false, Operator::IS->evaluate(false, Type::NUMBER));
        $this->assertSame(false, Operator::IS->evaluate('0', Type::FALSE));
        $this->assertSame(false, Operator::IS->evaluate('', Type::BOOLEAN));
        $this->assertSame(false, Operator::IS->evaluate(null, Type::FLOAT));
        $this->assertSame(false, Operator::IS->evaluate(null, Type::ARRAY));
        $this->assertSame(false, Operator::IS->evaluate('test', Type::TRUE));
        $this->assertSame(false, Operator::IS->evaluate(['test'], Type::NUMBER));
        $this->assertSame(false, Operator::IS->evaluate(1, Type::OBJECT));
    }

    public function testUnsupportedOperandObjectAsClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operand must be an instance of FQL\Enum\Type');
        Operator::IS->evaluate('test', new \stdClass());
    }

    public function testUnsupportedOperandNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operand must be an instance of FQL\Enum\Type');
        Operator::IS->evaluate('test', null);
    }

    public function testUnsupportedOperandTrue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operand must be an instance of FQL\Enum\Type');
        Operator::IS->evaluate('test', true);
    }

    public function testUnsupportedOperandFalse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operand must be an instance of FQL\Enum\Type');
        Operator::IS->evaluate('test', false);
    }

    public function testEvaluateIsNot(): void
    {
        $this->assertSame(false, Operator::NOT_IS->evaluate(null, Type::NULL));
        $this->assertSame(false, Operator::NOT_IS->evaluate(0, Type::INTEGER));
        $this->assertSame(false, Operator::NOT_IS->evaluate(0.0, Type::FLOAT));
        $this->assertSame(false, Operator::NOT_IS->evaluate(0, Type::NUMBER));
        $this->assertSame(false, Operator::NOT_IS->evaluate('0', Type::STRING));
        $this->assertSame(false, Operator::NOT_IS->evaluate('', Type::STRING));
        $this->assertSame(false, Operator::NOT_IS->evaluate('test', Type::STRING));
        $this->assertSame(false, Operator::NOT_IS->evaluate(true, Type::TRUE));
        $this->assertSame(false, Operator::NOT_IS->evaluate(false, Type::FALSE));
        $this->assertSame(false, Operator::NOT_IS->evaluate(true, Type::BOOLEAN));
        $this->assertSame(false, Operator::NOT_IS->evaluate(false, Type::BOOLEAN));
        $this->assertSame(false, Operator::NOT_IS->evaluate([], Type::ARRAY));
        $this->assertSame(false, Operator::NOT_IS->evaluate(new \stdClass(), Type::OBJECT));

        $this->assertSame(true, Operator::NOT_IS->evaluate(null, Type::NUMBER));
        $this->assertSame(true, Operator::NOT_IS->evaluate(0, Type::NULL));
        $this->assertSame(true, Operator::NOT_IS->evaluate(0.0, Type::NULL));
        $this->assertSame(true, Operator::NOT_IS->evaluate(0, Type::STRING));
        $this->assertSame(true, Operator::NOT_IS->evaluate(true, Type::STRING));
        $this->assertSame(true, Operator::NOT_IS->evaluate(false, Type::NUMBER));
        $this->assertSame(true, Operator::NOT_IS->evaluate('0', Type::FALSE));
        $this->assertSame(true, Operator::NOT_IS->evaluate('', Type::BOOLEAN));
        $this->assertSame(true, Operator::NOT_IS->evaluate(null, Type::FLOAT));
        $this->assertSame(true, Operator::NOT_IS->evaluate(null, Type::ARRAY));
        $this->assertSame(true, Operator::NOT_IS->evaluate('test', Type::TRUE));
        $this->assertSame(true, Operator::NOT_IS->evaluate(['test'], Type::NUMBER));
        $this->assertSame(true, Operator::NOT_IS->evaluate(1, Type::OBJECT));
    }
}
