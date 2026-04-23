<?php

namespace SQL\Runtime;

use FQL\Exception\UnknownFunctionException;
use FQL\Sql\Runtime\FunctionInvoker;
use PHPUnit\Framework\TestCase;

class FunctionInvokerTest extends TestCase
{
    public function testInvokesRegisteredScalarFunction(): void
    {
        $invoker = new FunctionInvoker();
        $this->assertSame('hello', $invoker->invoke('LOWER', ['HELLO']));
    }

    public function testInvokesAggregateStillWorksAtTopLevel(): void
    {
        $invoker = new FunctionInvoker();
        $this->assertTrue($invoker->isAggregate('SUM'));
        $this->assertFalse($invoker->isAggregate('LOWER'));
    }

    public function testHasPredicateReflectsRegistry(): void
    {
        $invoker = new FunctionInvoker();
        $this->assertTrue($invoker->has('LOWER'));
        $this->assertTrue($invoker->has('lower'));   // case-insensitive
        $this->assertFalse($invoker->has('NOPE_FUNCTION'));
    }

    public function testUnknownFunctionThrows(): void
    {
        $invoker = new FunctionInvoker();
        $this->expectException(UnknownFunctionException::class);
        $invoker->invoke('NOT_A_REAL_FUNCTION_XYZ', ['a']);
    }
}
